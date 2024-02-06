<?php

declare(strict_types=1);

namespace Minvws\Zammad\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ArticleAttachmentLinkReplacerTwigFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('replace_article_attachment_links', [$this, 'replaceAttachmentLinks']),
        ];
    }

    public function replaceAttachmentLinks(string $data, array $article): string
    {
        $attachments = $article['attachments'] ?? [];
        if (!is_array($attachments) || !count($attachments)) {
            return $data;
        }

        $ticketId = $article['ticket_id'];
        $articleId = $article['id'];

        foreach ($attachments as $attachment) {
            $attachmentId = $attachment['id'];

            $oldLink = $this->getOldAttachmentLink($ticketId, $articleId, $attachmentId);
            $newLink = $this->getNewAttachmentLink($articleId, $attachment['filename']);
            $data = str_replace($oldLink, $newLink, $data);
        }

        return $data;
    }

    protected function getOldAttachmentLink(int $ticketId, int $articleId, int $attachmentId): string
    {
        return "/api/v1/ticket_attachment/$ticketId/$articleId/$attachmentId?view=inline";
    }

    protected function getNewAttachmentLink(int $articleId, string $attachmentFilename): string
    {
        return "articles/$articleId/$attachmentFilename";
    }
}
