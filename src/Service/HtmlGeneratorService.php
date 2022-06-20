<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use Twig\Environment;
use ZammadAPIClient\Resource\Ticket;

class HtmlGeneratorService implements Generator
{
    protected Environment $twig;

    /**
     * @param Environment $twig
     */
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }


    public function generateIndex(string $path, array $tickets): void
    {
        $html = $this->twig->render('index.html.twig', [
            'tickets' => $tickets,
        ]);

        @mkdir($path, 0777, true);
        file_put_contents($path . "/export-".time().".html", $html);
    }

    public function generateTicket(string $path, Ticket $ticket, array $tags, array $history): void
    {
        $articles = [];
        foreach ($ticket->getTicketArticles() as $article) {
            $articles[] = $article->getValues();
        }

        $html = $this->twig->render('ticket.html.twig', [
            'ticket' => $ticket->getValues(),
            'articles' => $articles,
            'tags' => $tags,
            'history' => $history,
        ]);

        file_put_contents($path . "/ticket.html", $html);
    }
}
