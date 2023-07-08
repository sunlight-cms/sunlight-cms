<?php

namespace Sunlight\Search\Source;

use Sunlight\Article;
use Sunlight\Database\Database as DB;
use Sunlight\GenericTemplates;
use Sunlight\Post\Post;
use Sunlight\Post\PostService;
use Sunlight\Router;
use Sunlight\Search\SearchResult;
use Sunlight\User;
use Sunlight\Util\StringManipulator;

class PostSource extends FulltextSource
{
    /** @var string */
    private $columns;
    /** @var string */
    private $joins;
    /** @var string */
    private $filter;
    /** @var array */
    private $userQuery;

    function __construct(string $key)
    {
        parent::__construct($key);

        [$this->columns, $this->joins, $this->filter] = Post::createFilter('post', [
            Post::SECTION_COMMENT,
            Post::ARTICLE_COMMENT,
            Post::BOOK_ENTRY,
            Post::FORUM_TOPIC,
            Post::PLUGIN
        ]);

        $this->userQuery = User::createQuery('post.author');
    }

    protected function getTable(): string
    {
        return DB::table('post');
    }

    protected function getTableAlias(): ?string
    {
        return 'post';
    }

    protected function getFulltextColumns(): array
    {
        return [
            'post.subject',
            'post.text',
        ];
    }

    protected function getResultColumns(): array
    {
        return [$this->columns, $this->userQuery['column_list']];
    }

    protected function getJoins(): array
    {
        return [$this->joins, $this->userQuery['joins']];
    }

    protected function getFilter(): array
    {
        return [$this->filter];
    }

    protected function hydrateResult(SearchResult $result, array $row): void
    {
        $result->link = Router::postPermalink($row['id']);
        $result->title = PostService::getPostTitle($row);
        $result->perex = StringManipulator::ellipsis(strip_tags(Post::render($row['text'])), 255);

        if ($row['author'] == -1) {
            $result->infos[] = [_lang('global.postauthor'), '<span class="post-author-guest">' . PostService::renderGuestName($row['guest']) . '</span>'];
        } else {
            $result->infos[] = [_lang('global.postauthor'), Router::userFromQuery($this->userQuery, $row)];
        }

        $result->infos[] = [_lang('global.time'), GenericTemplates::renderTime($row['time'], 'post')];
    }
}
