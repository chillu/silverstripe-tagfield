<?php

namespace SilverStripe\TagField\Tests\Stub;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;

/**
 * @property int Sort
 * @method ManyManyList|TagFieldTestBlogPost[] BlogPosts()
 */
class TagFieldTestBlogTag extends DataObject implements TestOnly
{
    private static $table_name = 'TagFieldTestBlogTag';

    private static $default_sort = '"TagFieldTestBlogTag"."ID" ASC';

    private static $db = [
        'Title' => 'Varchar(200)',
        'Sort' => 'Int',
    ];

    private static $belongs_many_many = [
        'BlogPosts' => TagFieldTestBlogPost::class
    ];

    public function getLabel(): string
    {
        return 'Label: ' . $this->Title;
    }
}
