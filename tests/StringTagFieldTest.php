<?php

namespace SilverStripe\TagField\Tests;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\TagField\StringTagField;
use SilverStripe\TagField\Tests\Stub\StringTagFieldTestBlogPost;

class StringTagFieldTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'StringTagFieldTest.yml';

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        StringTagFieldTestBlogPost::class,
    ];

    public function testItSavesTagsOnNewRecords()
    {
        $record = $this->getNewStringTagFieldTestBlogPost('BlogPost1');

        $field = new StringTagField('Tags');
        $field->setValue(['Tag1', 'Tag2']);
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
        $field->setValue(['Tag1', 'Tag2']);
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

    public function testGetSchemaDataDefaults()
    {
        $form = new Form(null, 'Form', new FieldList(), new FieldList());
        $field = new StringTagField('TestField', 'Test Field', ['one', 'two']);
        $field->setForm($form);

        $field
            ->setShouldLazyLoad(false)
            ->setCanCreate(false);

        $schema = $field->getSchemaDataDefaults();
        $this->assertSame('TestField[]', $schema['name']);
        $this->assertFalse($schema['lazyLoad']);
        $this->assertFalse($schema['creatable']);
        $this->assertEquals([
            ['Title' => 'one', 'Value' => 'one'],
            ['Title' => 'two', 'Value' => 'two'],
        ], $schema['options']);

        $field
            ->setShouldLazyLoad(true)
            ->setCanCreate(true);

        $schema = $field->getSchemaDataDefaults();
        $this->assertTrue($schema['lazyLoad']);
        $this->assertTrue($schema['creatable']);
        $this->assertStringContainsString('suggest', $schema['optionUrl']);
    }

    public function testPerformReadonlyTransformation()
    {
        $field = new StringTagField('Tags');
        $field->setSource(['Test1', 'Test2', 'Test3']);

        // Ensure a single value can be rendered
        $field->setValue(['Test2']);
        $field_readonly = $field->performReadonlyTransformation();
        $this->assertEquals('Test2', $field_readonly->getValue());

        // Ensure multiple valid values are rendered
        $field->setValue(['Test1', 'Test2']);
        $field_readonly = $field->performReadonlyTransformation();
        $this->assertEquals('Test1, Test2', $field_readonly->getValue());

        // Ensure an value not in the source array is still rendered
        // (because e.g. in history view it must have been a valid value when it was set)
        $field->setValue(['Test', 'Test1']);
        $field_readonly = $field->performReadonlyTransformation();
        $this->assertEquals('Test, Test1', $field_readonly->getValue());
    }

    /**
     * @param array $parameters
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
