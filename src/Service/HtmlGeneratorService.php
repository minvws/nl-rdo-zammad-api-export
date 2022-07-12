<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use Minvws\Zammad\Path;
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

    public function generateIndex(Path $path, array $data): void
    {
        $html = $this->twig->render('index.html.twig', [
            'data' => $data,
        ]);

        @mkdir($path->getPath(), 0777, true);

        file_put_contents($path->add("export-" . time() . ".html")->getPath(), $html);
    }

    public function generateFullIndex(Path $path, array $data): void
    {
        $html = $this->twig->render('index_full.html.twig', [
            'data' => $data,
        ]);

        @mkdir($path->getPath(), 0777, true);
        file_put_contents($path->add("export-full-" . time() . ".html")->getPath(), $html);
    }


    public function generateGroupIndex(Path $path, array $data): void
    {
        $html = $this->twig->render('groupindex.html.twig', [
            'group' => $data,
        ]);

        @mkdir($path->getPath(), 0777, true);
        file_put_contents($path->add('index.html')->getPath(), $html);
    }

    public function generateTicket(Path $path, Ticket $ticket, array $tags, array $history): void
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

        file_put_contents($path->add('ticket.html')->getPath(), $html);
    }
}
