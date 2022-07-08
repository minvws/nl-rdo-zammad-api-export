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

    public function generateIndex(string $basePath, array $data): void
    {
        $html = $this->twig->render('index.html.twig', [
            'data' => $data,
        ]);

        $basePath = explode("/", $basePath);
        $path = Sanitize::path($basePath);
        @mkdir($path, 0777, true);

        $path = Sanitize::path($basePath, "export-".time().".html");
        file_put_contents($path, $html);
    }

    public function generateFullIndex(string $path, array $data): void
    {
        $html = $this->twig->render('index_full.html.twig', [
            'data' => $data,
        ]);

        @mkdir($path, 0777, true);
        file_put_contents($path . "/export-full-".time().".html", $html);
    }


    public function generateGroupIndex(string $basePath, array $data): void
    {
        $html = $this->twig->render('groupindex.html.twig', [
            'group' => $data,
        ]);

        $basePath = explode("/", $basePath);
        $path = Sanitize::path($basePath);
        @mkdir($path, 0777, true);

        $path = Sanitize::path($basePath, "index.html");
        file_put_contents($path, $html);
    }

    public function generateTicket(string $basePath, Ticket $ticket, array $tags, array $history): void
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

        $basePath = explode("/", $basePath);
        $path = Sanitize::path($basePath, "ticket.html");
        file_put_contents($path, $html);
    }
}
