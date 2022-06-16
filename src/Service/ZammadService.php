<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use Minvws\Zammad\Resource\TicketHistory;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use ZammadAPIClient\Client;
use ZammadAPIClient\Resource\Group;
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

    public function export(string $group, string $destinationPath, int $percentage)
    {
        $group = $this->getGroup($group);
        if (!$group) {
            throw new \Exception("Group $group not found");
        }

        $result = [
            'tickets' => [],
        ];

        $tickets = $this->client->resource(ResourceType::TICKET)->all();
        foreach ($tickets as $ticket) {
            /** @var Ticket $ticket */
            if ($ticket->getValue('group_id') != $group->getID()) {
                continue;
            }

            // Fetch the MD5 of the ticket number (md5 should be evenly distributed). Fetch the first 2 digits to get
            // a 0..255 value..  use the percentage (ie: 10% = 25, 50% =  128) to check if this ticket needs to be
            // exported or not
            $hash = md5($ticket->getValue('number'));
            $hashValue = hexdec(substr($hash, 0, 2));
            $minValue = intval(round($percentage * 2.55));
            if ($hashValue > $minValue) {
                continue;
            }

            $this->output->writeln("* Dumping ticket " . $ticket->getID() . ' : '. $ticket->getValue('title'));

            $date = new \DateTime($ticket->getValue('created_at'));

            $ticketPath = $group->getValue('name') . "/";
            $ticketPath .= $date->format('Y-m') . "/";
            $ticketPath .= $ticket->getValue('number');

            @mkdir($destinationPath . "/" . $ticketPath, 0777, true);
            @mkdir($destinationPath . "/" . $ticketPath . "/articles", 0777, true);

            // Dump ticket data
            $data = json_encode($ticket->getValues(), JSON_PRETTY_PRINT);
            file_put_contents($destinationPath . "/" . $ticketPath . "/ticket.json", $data);

            $result['tickets'][] = [
                'data' => $ticket->getValues(),
                'path' => $ticketPath,
            ];

            // Dump tags
            /** @var Tag $tag */
            $tag = $this->client->resource(ResourceType::TAG)->get($ticket->getID(), 'Ticket');
            $tags = $tag->getValue('tags');
            file_put_contents($destinationPath . "/" . $ticketPath . "/tags.json", json_encode($tags, JSON_PRETTY_PRINT));

            // Dump history
            $history = $this->client->resource(TicketHistory::class)->get($ticket->getID());
            $history = $history->getValues()['history'] ?? [];
            file_put_contents($destinationPath . "/" . $ticketPath . "/history.json", json_encode($history, JSON_PRETTY_PRINT));

            // Articles
            $articles = $ticket->getTicketArticles();
            foreach($articles as $article) {
                $data = json_encode($article->getValues(), JSON_PRETTY_PRINT);

                // Save article data
                $articlePath = $destinationPath . "/" . $ticketPath . "/articles/" . $article->getID();
                @mkdir($articlePath, 0777, true);
                file_put_contents($articlePath . "/article.json", $data);

                // Attachments
                foreach($article->getValue('attachments') as $attachment) {
                    $content = $article->getAttachmentContent($attachment['id']);
                    file_put_contents($articlePath . "/". $attachment['filename'], $content);
                }
            }

            $this->generator->generateTicket($destinationPath . "/" . $ticketPath, $ticket, $tags, $history);
        }

        $this->generator->generateIndex($destinationPath, $result['tickets']);
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    protected function getGroup(string $groupName): ?Group
    {
        $groups = $this->client->resource(ResourceType::GROUP)->all();
        foreach ($groups as $group) {
            if (strtolower($group->getValue('name')) == strtolower($groupName)) {
                return $group;
            }
        }

        return null;
    }
}
