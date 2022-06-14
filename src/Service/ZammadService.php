<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use ZammadAPIClient\Client;
use ZammadAPIClient\Resource\Tag;
use ZammadAPIClient\Resource\Ticket;
use ZammadAPIClient\ResourceType;

class ZammadService
{
    protected Client $client;
    protected Generator $generator;
    protected OutputInterface $output;


    /**
     * @param string $url
     * @param string $token
     */
    public function __construct(string $url, string $token, HtmlGeneratorService $generator)
    {
        $this->generator = $generator;
        $this->output = new NullOutput();

        $this->client = new Client([
            'url' => $url,
            'http_token' => $token,
            'timeout' => 15,
            'debug' => false,
            'verify' => true,
        ]);
    }

    public function export(string $email, string $destinationPath)
    {
        // Fetch user
        $user = $this->client->resource(ResourceType::USER)->search($email);
        if (count($user) == 0) {
            throw new \Exception("User $email not found");
        }
        $user = $user[0];

        // Fetch everything for this user only
        $this->client->setOnBehalfOfUser($user->getId());

        $result = [
            'tickets' => [],
        ];

        $tickets = $this->client->resource(ResourceType::TICKET)->all();
        foreach ($tickets as $ticket) {
            /** @var Ticket $ticket */
            $this->output->writeln("* Dumping ticket " . $ticket->getID() . ' : '. $ticket->getValue('title'));

            $ticketPath = $destinationPath . "/". $email . "/" . $ticket->getValue('number');
            @mkdir($ticketPath, 0777, true);
            @mkdir($ticketPath . "/articles", 0777, true);

            // Dump ticket data
            $data = json_encode($ticket->getValues(), JSON_PRETTY_PRINT);
            file_put_contents($ticketPath . "/ticket.json", $data);

            $result['tickets'][] = $ticket->getValues();

            // Dump tags
            /** @var Tag $tag */
            $tag = $this->client->resource(ResourceType::TAG)->get($ticket->getID(), 'Ticket');
            $tags = $tag->getValue('tags');
            file_put_contents($ticketPath . "/tags.json", json_encode($tags, JSON_PRETTY_PRINT));

            // Articles
            $articles = $ticket->getTicketArticles();
            foreach($articles as $article) {
                $data = json_encode($article->getValues(), JSON_PRETTY_PRINT);

                // Save article data
                $articlePath = $ticketPath . "/articles/" . $article->getID();
                @mkdir($articlePath, 0777, true);
                file_put_contents($articlePath . "/article.json", $data);

                // Attachments
                foreach($article->getValue('attachments') as $attachment) {
                    $content = $article->getAttachmentContent($attachment['id']);
                    file_put_contents($articlePath . "/". $attachment['filename'], $content);
                }
            }


            $this->generator->generateTicket($ticketPath, $ticket);
        }

        $this->generator->generateIndex($destinationPath, $email."/", $result['tickets']);
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }
}
