<?php

namespace SilverStripe\TagField\Tests;

use ReflectionMethod;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\TagField\ReadonlyTagField;
use SilverStripe\TagField\TagField;
use SilverStripe\TagField\Tests\Stub\TagFieldTestBlogPost;
use SilverStripe\TagField\Tests\Stub\TagFieldTestBlogTag;
use SilverStripe\TagField\Tests\Stub\TagFieldTestController;

class TagFieldTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'TagFieldTest.yml';

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        TagFieldTestBlogTag::class,
        TagFieldTestBlogPost::class,
    ];

    public function testItSavesLinksToNewTagsOnNewRecords()
    {
        $record = $this->getNewTagFieldTestBlogPost('BlogPost1');
        $field = new TagField('Tags', '', new DataList(TagFieldTestBlogTag::class));
        $field->setValue(['Tag3', 'Tag4']);
        $field->saveInto($record);
        $record->write();

        $this->compareExpectedAndActualTags(
            ['Tag3', 'Tag4'],
            $record
        );
    }

    public function testItSavesToHasOne()
    {
        $record = $this->getNewTagFieldTestBlogPost('BlogPost1');
        $tag = new TagFieldTestBlogTag();
        $tag->Title = 'Foobar';
        $tag->write();

        $field = new TagField('PrimaryTagID', '', new DataList(TagFieldTestBlogTag::class));
        $field->setIsMultiple(false);

        $field->setValue('Foobar');
        $field->saveInto($record);
        $record->write();

        $this->assertEquals($tag->ID, $record->PrimaryTagID, 'The tag is saved to a has_one');

        $tag = new TagFieldTestBlogTag();
        $tag->Title = 'Foobarbaz';
        $tag->write();

        $field->setValue(['Foobarbaz']);
        $field->saveInto($record);
        $record->write();

        $this->assertEquals($tag->ID, $record->PrimaryTagID, 'The tag is saved to a has_one');
    }

    /**
     * @dataProvider rawValueStoreCasesProvider
     */
    public function testItSavesToRawValue(bool $rawValueEnabled, mixed $values, mixed $expected): void
    {
        $record = $this->getNewTagFieldTestBlogPost('BlogPost1');
        $tag = new TagFieldTestBlogTag();
        $tag->Title = 'RawValueTest';
        $tag->write();

        $field = new TagField('InMemoryOnlyProperty', '', new DataList(TagFieldTestBlogTag::class));
        $field->setAllowRawValue($rawValueEnabled);

        $field->setValue($values);
        $field->saveInto($record);

        $this->assertEquals($expected, $record->InMemoryOnlyProperty, 'We expect raw value to be stored');
    }

    public function rawValueStoreCasesProvider(): array
    {
        return [
            'raw value disabled' => [
                false,
                'SampleValue1',
                null,
            ],
            'raw value enabled (single value)' => [
                true,
                'SampleValue1',
                [
                    'SampleValue1',
                ],
            ],
            'raw value enabled (multiple values)' => [
                true,
                [
                    'SampleValue1',
                    'SampleValue2',
                ],
                [
                    'SampleValue1',
                    'SampleValue2',
                ],
            ],
        ];
    }

    /**
     * @dataProvider rawValueLoadCasesProvider
     */
    public function testItLoadsFromRawValue(bool $rawValueEnabled, mixed $values, mixed $expected): void
    {
        $record = $this->getNewTagFieldTestBlogPost('BlogPost1');
        $tag = new TagFieldTestBlogTag();
        $tag->Title = 'RawValueTest';
        $tag->write();

        $field = new TagField('InMemoryOnlyProperty', '', new DataList(TagFieldTestBlogTag::class));
        $field->setAllowRawValue($rawValueEnabled);
        $record->InMemoryOnlyProperty = $values;

        $field->loadFrom($record);

        $this->assertEquals($expected, $field->Value(), 'We expect raw value to be loaded');
    }

    public function rawValueLoadCasesProvider(): array
    {
        return [
            'raw value disabled' => [
                false,
                null,
                [],
            ],
            'raw value enabled (single value)' => [
                true,
                [
                    'SampleValue1',
                ],
                [
                    'SampleValue1',
                ],
            ],
            'raw value enabled (multiple values)' => [
                true,
                [
                    'SampleValue1',
                    'SampleValue2',
                ],
                [
                    'SampleValue1',
                    'SampleValue2',
                ],
            ],
        ];
    }

    /**
     * @param string $name
     *
     * @return TagFieldTestBlogPost
     */
    protected function getNewTagFieldTestBlogPost($name)
    {
        return $this->objFromFixture(
            TagFieldTestBlogPost::class,
            $name
        );
    }

    /**
     * @param array $expected
     * @param TagFieldTestBlogPost $record
     */
    protected function compareExpectedAndActualTags(array $expected, TagFieldTestBlogPost $record)
    {
        $this->compareTagLists($expected, $record->Tags());
    }

    /**
     * Ensure a source of tags matches the given string tag names
     *
     * @param array $expected
     * @param DataList $actualSource
     */
    protected function compareTagLists(array $expected, DataList $actualSource)
    {
        $actual = array_values($actualSource->map('ID', 'Title')->toArray() ?? []);
        sort($expected);
        sort($actual);

        $this->assertEquals(
            $expected,
            $actual
        );
    }

    public function testItSavesLinksToNewTagsOnExistingRecords()
    {
        $record = $this->getNewTagFieldTestBlogPost('BlogPost1');
        $record->write();

        $field = new TagField('Tags', '', new DataList(TagFieldTestBlogTag::class));
        $field->setValue(['Tag3', 'Tag4']);
        $field->saveInto($record);

        $this->compareExpectedAndActualTags(
            array('Tag3', 'Tag4'),
            $record
        );
    }

    public function testItSavesLinksToExistingTagsOnNewRecords()
    {
        $record = $this->getNewTagFieldTestBlogPost('BlogPost1');

        $field = new TagField('Tags', '', new DataList(TagFieldTestBlogTag::class));
        $field->setValue(['Tag1', 'Tag2']);
        $field->saveInto($record);

        $record->write();

        $this->compareExpectedAndActualTags(
            ['Tag1', 'Tag2'],
            $record
        );
    }

    public function testItSavesLinksToExistingTagsOnExistingRecords()
    {
        $record = $this->getNewTagFieldTestBlogPost('BlogPost1');
        $record->write();

        $field = new TagField('Tags', '', new DataList(TagFieldTestBlogTag::class));
        $field->setValue(['Tag1', 'Tag2']);
        $field->saveInto($record);

        $this->compareExpectedAndActualTags(
            ['Tag1', 'Tag2'],
            $record
        );
    }

    public function testSavesReactTags()
    {
        $record = $this->getNewTagFieldTestBlogPost('BlogPost1');
        $record->write();

        $field = new TagField('Tags', '', new DataList(TagFieldTestBlogTag::class));
        $field->setValue([
            [
                'Title' => 'Tag1',
                'Value' => 'Tag1',
            ],
            [
                'Title' => 'Tag2',
                'Value' => 'Tag2',
            ],
        ]);
        $field->saveInto($record);

        $this->compareExpectedAndActualTags(
            ['Tag1', 'Tag2'],
            $record
        );
    }

    /**
     * Ensure that {@see TagField::saveInto} respects existing tags
     */
    public function testSaveDuplicateTags()
    {
        $record = $this->getNewTagFieldTestBlogPost('BlogPost2');
        $record->write();
        $tag2ID = $this->idFromFixture(TagFieldTestBlogTag::class, 'Tag2');

        // Check tags before write
        $this->compareExpectedAndActualTags(
            ['Tag1', '222'],
            $record
        );
        $this->compareTagLists(
            ['Tag1', '222'],
            TagFieldTestBlogTag::get()
        );
        $this->assertContains($tag2ID, TagFieldTestBlogTag::get()->column('ID'));

        // Write new tags
        $field = new TagField('Tags', '', new DataList(TagFieldTestBlogTag::class));
        $field->setValue(['222', 'Tag3']);
        $field->saveInto($record);

        // Check only one new tag was added
        $this->compareExpectedAndActualTags(
            ['222', 'Tag3'],
            $record
        );

        // Ensure that only one new dataobject was added and that tag2s id has not changed
        $this->compareTagLists(
            ['Tag1', '222', 'Tag3'],
            TagFieldTestBlogTag::get()
        );
        $this->assertContains($tag2ID, TagFieldTestBlogTag::get()->column('ID'));
    }

    public function testItSuggestsTags()
    {
        $field = new TagField('Tags', '', new DataList(TagFieldTestBlogTag::class));

        /**
         * Partial tag title match.
         */
        $request = $this->getNewRequest(['term' => 'Tag']);

        $this->assertEquals(
            '{"items":[{"Title":"Tag1","Value":"Tag1"}]}',
            $field->suggest($request)->getBody()
        );

        /**
         * Exact tag title match.
         */
        $request = $this->getNewRequest(['term' => '222']);

        $this->assertEquals(
            '{"items":[{"Title":"222","Value":"222"}]}',
            $field->suggest($request)->getBody()
        );

        /**
         * Case-insensitive tag title match.
         */
        $request = $this->getNewRequest(['term' => 'TAG1']);

        $this->assertEquals(
            '{"items":[{"Title":"Tag1","Value":"Tag1"}]}',
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
     * Tests that TagField supports pre-filtered data sources
     */
    public function testRestrictedSuggestions()
    {
        $source = TagFieldTestBlogTag::get()->exclude('Title', 'Tag2');
        $field = new TagField('Tags', '', $source);

        /**
         * Partial tag title match.
         */
        $request = $this->getNewRequest(['term' => 'Tag']);

        $this->assertEquals(
            '{"items":[{"Title":"Tag1","Value":"Tag1"}]}',
            $field->suggest($request)->getBody()
        );

        /**
         * Exact tag title match.
         */
        $request = $this->getNewRequest(['term' => 'Tag1']);

        $this->assertEquals(
            '{"items":[{"Title":"Tag1","Value":"Tag1"}]}',
            $field->suggest($request)->getBody()
        );

        /**
         * Excluded item doesn't appear in matches
         */
        $request = $this->getNewRequest(['term' => 'Tag2']);

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
            'TagFieldTestController/TagFieldTestForm/fields/Tags/suggest',
            $parameters
        );
    }

    public function testItDisplaysValuesFromRelations()
    {
        $record = $this->getNewTagFieldTestBlogPost('BlogPost1');
        $record->write();

        $form = new Form(
            new TagFieldTestController(),
            'Form',
            new FieldList(
                $field = new TagField('Tags', '', new DataList(TagFieldTestBlogTag::class))
            ),
            new FieldList()
        );

        $form->loadDataFrom(
            $this->objFromFixture(TagFieldTestBlogPost::class, 'BlogPost2')
        );

        $ids = TagFieldTestBlogTag::get()->column('ID');

        $this->assertEquals($field->Value(), $ids);
    }

    public function testItIgnoresNewTagsIfCannotCreate()
    {
        $this->markTestSkipped(
            'This test has not been updated yet.'
        );

        $record = new TagFieldTestBlogPost();
        $record->write();

        $tag = TagFieldTestBlogTag::get()->filter('Title', 'Tag1')->first();

        $field = new TagField('Tags', '', new DataList(TagFieldTestBlogTag::class), [$tag->Title, 'Tag3']);
        $field->setCanCreate(false);
        $field->saveInto($record);

        /**
         * @var TagFieldTestBlogPost $record
         */
        $record = DataObject::get_by_id(TagFieldTestBlogPost::class, $record->ID);

        $this->compareExpectedAndActualTags(
            ['Tag1'],
            $record
        );
    }

    /**
     * Test you can save without a source set
     */
    public function testSaveEmptySource()
    {
        $record = new TagFieldTestBlogPost();
        $record->write();

        // Clear database of tags
        TagFieldTestBlogTag::get()->removeAll();

        $field = new TagField('Tags', '', TagFieldTestBlogTag::get());
        $field->setValue(['New Tag']);
        $field->setCanCreate(true);
        $field->saveInto($record);

        $tag = TagFieldTestBlogTag::get()->first();
        $this->assertNotEmpty($tag);
        $this->assertEquals('New Tag', $tag->Title);

        $record = TagFieldTestBlogPost::get()->byID($record->ID);

        $this->assertEquals(
            $tag->ID,
            $record->Tags()->first()->ID
        );
    }

    /**
     * Test read only fields are returned
     */
    public function testReadonlyTransformation()
    {
        $field = new TagField('Tags', '', TagFieldTestBlogTag::get());
        $readOnlyField = $field->performReadonlyTransformation();
        $this->assertInstanceOf(ReadonlyTagField::class, $readOnlyField);

        // Custom title field
        $field = new TagField('Tags', '', TagFieldTestBlogTag::get());
        $field->setTitleField('Name');
        $readOnlyField = $field->performReadonlyTransformation();
        $this->assertEquals('Name', $readOnlyField->getTitleField());

        // Also check Field options
        $field = new TagField('Tags', '', TagFieldTestBlogTag::get());
        $field->setTitleField('Title');
        $field->setValue(['Tag1']);

        // When not read only (and not lazy-loading) all source options are returned
        $htmlText = $field->Field();
        $this->assertStringContainsString('Tag1', $htmlText);
        $this->assertStringContainsString('222', $htmlText);

        // When read only mode, only selected options are returned
        $readOnlyField = $field->performReadonlyTransformation();
        $htmlText = $readOnlyField->Field();
        $this->assertStringContainsString('Tag1', $htmlText);
        $this->assertStringNotContainsString('222', $htmlText);
    }

    public function testItDisplaysWithSelectedValuesFromDataList()
    {
        $source = TagFieldTestBlogTag::get();
        $selectedTag = $source->First();
        $unselectedTag = $source->Last();
        $value = $source->filter('ID', $selectedTag->ID); // arbitrary subset

        $field = new TagField('TestField', null, $source, $value);

        // Not the cleanest way to assert this, but getOptions() is protected
        $schema = $field->getSchemaDataDefaults();
        $this->assertTrue(
            $this->getFromOptionsByTitle($schema['options'], $selectedTag->Title)['Selected']
        );
        $this->assertFalse(
            $this->getFromOptionsByTitle($schema['options'], $unselectedTag->Title)['Selected']
        );
    }

    /**
     * @dataProvider optionCasesProvider
     */
    public function testGetOptionsWithConfigurableFields(
        ?string $titleField,
        ?string $valueField,
        array $expected
    ): void {
        $source = TagFieldTestBlogTag::get();

        $field = new TagField('TestField', null, $source);

        if ($titleField) {
            $field->setTitleField($titleField);
        }

        if ($valueField) {
            $field->setValueField($valueField);
        }

        /** @see TagField::getOptions() */
        $getOptionsMethod = new ReflectionMethod($field, 'getOptions');

        /** @var ArrayList $result */
        $result = $getOptionsMethod->invoke($field);

        $data = $result
            ->map('Title', 'Value')
            ->toArray();
        $this->assertSame($expected, $data, 'We expect specific fields to be present');
    }

    public function optionCasesProvider(): array
    {
        return [
            'default fields' => [
                null,
                null,
                [
                    'Tag1' => 'Tag1',
                    '222' => '222'
                ],
            ],
            'Label > Sort' => [
                'Label',
                'Sort',
                [
                    'Label: Tag1' => 2,
                    'Label: 222' => 1
                ],
            ],
            'Sort > Title' => [
                'Sort',
                'Title',
                [
                    2 => 'Tag1',
                    1 => '222'
                ],
            ],
        ];
    }

    /**
     * @dataProvider getTagsCasesProvider
     */
    public function testGetTagsWithConfigurableFields(
        ?string $titleField,
        ?string $valueField,
        ?string $searchField,
        ?string $sortField,
        string $searchSubject,
        array $expected
    ): void {
        $tag3 = TagFieldTestBlogTag::create();
        $tag3->Title = 'Tag3';
        $tag3->Sort = 3;
        $tag3->write();

        $source = TagFieldTestBlogTag::get();

        $field = new TagField('TestField', null, $source);

        if ($titleField) {
            $field->setTitleField($titleField);
        }

        if ($valueField) {
            $field->setValueField($valueField);
        }

        if ($searchField) {
            $field->setSearchField($searchField);
        }

        if ($sortField) {
            $field->setSortField($sortField);
        }

        /** @see TagField::getTags() */
        $getTagsMethod = new ReflectionMethod($field, 'getTags');

        /** @var ArrayList $result */
        $result = $getTagsMethod->invoke($field, $searchSubject);
        $data = [];

        foreach ($result as $item) {
            $title = $item['Title'];
            $value = $item['Value'];
            $data[$title] = $value;
        }

        $this->assertSame($expected, $data, 'We expect specific fields to be present');
    }

    public function getTagsCasesProvider(): array
    {
        return [
            'default fields' => [
                null,
                null,
                null,
                null,
                'Tag',
                [
                    'Tag1' => 'Tag1',
                    'Tag3' => 'Tag3',
                ],
            ],
            'custom fields' => [
                'Label',
                'Sort',
                'Title',
                'Sort',
                'Tag',
                [
                    'Label: Tag1' => 2,
                     'Label: Tag3' => 3,
                ],
            ],
        ];
    }

    public function testGetSchemaDataDefaults()
    {
        $form = new Form(null, 'Form', new FieldList(), new FieldList());
        $field = new TagField('TestField', 'Test Field', TagFieldTestBlogTag::get());
        $field->setForm($form);

        $field
            ->setShouldLazyLoad(false)
            ->setCanCreate(false);

        $schema = $field->getSchemaDataDefaults();
        $this->assertSame('TestField[]', $schema['name']);
        $this->assertFalse($schema['lazyLoad']);
        $this->assertFalse($schema['creatable']);
        $this->assertEquals([
            ['Title' => 'Tag1', 'Value' => 'Tag1', 'Selected' => false],
            ['Title' => '222', 'Value' => '222', 'Selected' => false],
        ], $schema['options']);

        $field->setValue(['222']);
        $schema = $field->getSchemaDataDefaults();

        $this->assertEquals([
            ['Title' => 'Tag1', 'Value' => 'Tag1', 'Selected' => false],
            ['Title' => '222', 'Value' => '222', 'Selected' => true],
        ], $schema['options']);

        $field
            ->setShouldLazyLoad(true)
            ->setCanCreate(true);

        $schema = $field->getSchemaDataDefaults();
        $this->assertTrue($schema['lazyLoad']);
        $this->assertTrue($schema['creatable']);
        $this->assertStringContainsString('suggest', $schema['optionUrl']);
    }

    public function testSchemaIsAddedToAttributes()
    {
        $field = new TagField('TestField');
        $attributes = $field->getAttributes();
        $this->assertNotEmpty($attributes['data-schema']);
    }

    /**
     * @param array $options
     * @param string $title
     * @return array|null
     */
    protected function getFromOptionsByTitle(array $options, $title)
    {
        foreach ($options as $option) {
            if ($option['Title'] == $title) {
                return $option;
            }
        }

        return null;
    }
}
