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


    public function generateIndex(string $path, string $basepath, array $tickets): void
    {
        $html = $this->twig->render('index.html.twig', [
            'tickets' => $tickets,
            'basepath' => $basepath,
        ]);

        file_put_contents($path . "/index.html", $html);
    }

    public function generateTicket(string $path, Ticket $ticket): void
    {
        $articles = [];
        foreach ($ticket->getTicketArticles() as $article) {
            $articles[] = $article->getValues();
        }

        $html = $this->twig->render('ticket.html.twig', [
            'ticket' => $ticket->getValues(),
            'articles' => $articles,
        ]);

        file_put_contents($path . "/ticket.html", $html);
    }
}
