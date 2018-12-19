<?php

namespace SilverStripe\TagField\Tests;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\TagField\StringTagField;
use SilverStripe\TagField\Tests\Stub\StringTagFieldTestBlogPost;

/**
 * @mixin PHPUnit_Framework_TestCase
 */
class StringTagFieldTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'StringTagFieldTest.yml';

    /**
     * @var array
     */
    protected static $extra_dataobjects = array(
        StringTagFieldTestBlogPost::class,
    );

    public function testItSavesTagsOnNewRecords()
    {
        $record = $this->getNewStringTagFieldTestBlogPost('BlogPost1');

        $field = new StringTagField('Tags');
        $field->setValue(array('Tag1', 'Tag2'));
        $field->saveInto($record);

        $record->write();

        $this->assertEquals('Tag1,Tag2', $record->Tags);
    }

    /**
     * @param string $name
     *
     * @return StringTagFieldTestBlogPost
     */
    protected function getNewStringTagFieldTestBlogPost($name)
    {
        return $this->objFromFixture(
            StringTagFieldTestBlogPost::class,
            $name
        );
    }

    public function testItSavesTagsOnExistingRecords()
    {
        $record = $this->getNewStringTagFieldTestBlogPost('BlogPost1');
        $record->write();

        $field = new StringTagField('Tags');
        $field->setValue(array('Tag1', 'Tag2'));
        $field->saveInto($record);

        $this->assertEquals('Tag1,Tag2', $record->Tags);
    }

    public function testItSuggestsTags()
    {
        $field = new StringTagField('SomeField', 'Some field', ['Tag1', 'Tag2'], []);

        /**
         * Partial tag title match.
         */
        $request = $this->getNewRequest(['term' => 'Tag']);

        $this->assertEquals(
            '{"items":[{"id":"Tag1","text":"Tag1"},{"id":"Tag2","text":"Tag2"}]}',
            $field->suggest($request)->getBody()
        );

        /**
         * Exact tag title match.
         */
        $request = $this->getNewRequest(['term' => 'Tag1']);

        $this->assertEquals($field->suggest($request)->getBody(), '{"items":[{"id":"Tag1","text":"Tag1"}]}');

        /**
         * Case-insensitive tag title match.
         */
        $request = $this->getNewRequest(['term' => 'TAG1']);

        $this->assertEquals(
            '{"items":[{"id":"Tag1","text":"Tag1"}]}',
            $field->suggest($request)->getBody()
        );

        /**
         * No tag title match.
         */
        $request = $this->getNewRequest(['term' => 'unknown']);

        $this->assertEquals(
            '{"items":[]}',
            $field->suggest($request)->getBody()
        );
    }

    /**
     * @param array $parameters
     *
     * @return HTTPRequest
     */
    protected function getNewRequest(array $parameters)
    {
        return new HTTPRequest(
            'get',
            'StringTagFieldTestController/StringTagFieldTestForm/fields/Tags/suggest',
            $parameters
        );
    }
}
