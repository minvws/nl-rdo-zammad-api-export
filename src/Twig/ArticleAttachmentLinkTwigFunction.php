<?php

declare(strict_types=1);

namespace Minvws\Zammad\Twig;

use Minvws\Zammad\Service\ArticleAttachmentLinkService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ArticleAttachmentLinkTwigFunction extends AbstractExtension
{
    public function __construct(
        protected ArticleAttachmentLinkService $articleAttachmentLinkService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_article_attachment_link', [$this, 'getAttachmentLink']),
        ];
    }

    public function getAttachmentLink(int $articleId, string $attachmentFilename): string
    {
        return $this->articleAttachmentLinkService->getAttachmentLink($articleId, $attachmentFilename);
    }
}
