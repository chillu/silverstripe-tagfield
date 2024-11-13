<?php

namespace SilverStripe\TagField;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\MultiSelectField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\FieldType\DBMultiEnum;
use SilverStripe\ORM\Relation;
use SilverStripe\Model\List\SS_List;
use SilverStripe\Model\ArrayData;
use SilverStripe\Core\Validation\FieldValidation\MultiOptionFieldValidator;

/**
 * Provides a tagging interface, storing links between tag DataObjects and a parent DataObject.
 *
 * @package forms
 * @subpackage fields
 */
class TagField extends MultiSelectField
{
    private static array $field_validators = [
        // Disable validation as TagField is a special case where labels can be the values
        // rather than the keys always being the values - for instance when creating new tags
        MultiOptionFieldValidator::class => null,
    ];

    /**
     * @var array
     */
    private static $allowed_actions = [
        'suggest',
    ];

    /**
     * @var bool
     */
    protected $shouldLazyLoad = false;

    /**
     * @var int
     */
    protected $lazyLoadItemLimit = 10;

    /**
     * @var bool
     */
    protected $canCreate = true;

    /**
     * @var string
     */
    protected $titleField = 'Title';

    /**
     * @var DataList
     */
    protected $sourceList;

    /**
     * @var bool
     */
    protected $isMultiple = true;

    protected $schemaComponent = 'TagField';

    /**
     * @param string $name
     * @param string $title
     * @param null|DataList|array $source
     * @param null|DataList $value
     * @param string $titleField
     */
    public function __construct($name, $title = '', $source = [], $value = null, $titleField = 'Title')
    {
        $this->setTitleField($titleField);
        parent::__construct($name, $title, $source, $value);

        $this->addExtraClass('ss-tag-field');
    }

    /**
     * @return bool
     */
    public function getShouldLazyLoad()
    {
        return $this->shouldLazyLoad;
    }

    /**
     * @param bool $shouldLazyLoad
     *
     * @return static
     */
    public function setShouldLazyLoad($shouldLazyLoad)
    {
        $this->shouldLazyLoad = $shouldLazyLoad;

        return $this;
    }

    /**
     * @return int
     */
    public function getLazyLoadItemLimit()
    {
        return $this->lazyLoadItemLimit;
    }

    /**
     * @param int $lazyLoadItemLimit
     *
     * @return static
     */
    public function setLazyLoadItemLimit($lazyLoadItemLimit)
    {
        $this->lazyLoadItemLimit = $lazyLoadItemLimit;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIsMultiple()
    {
        return $this->isMultiple;
    }

    /**
     * @param bool $isMultiple
     *
     * @return static
     */
    public function setIsMultiple($isMultiple)
    {
        $this->isMultiple = $isMultiple;

        return $this;
    }

    /**
     * @return bool
     */
    public function getCanCreate()
    {
        return $this->canCreate;
    }

    /**
     * @param bool $canCreate
     *
     * @return static
     */
    public function setCanCreate($canCreate)
    {
        $this->canCreate = $canCreate;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitleField()
    {
        return $this->titleField;
    }

    /**
     * @param string $titleField
     *
     * @return $this
     */
    public function setTitleField($titleField)
    {
        $this->titleField = $titleField;

        return $this;
    }

    /**
     * Get the DataList source. The 4.x upgrade for SelectField::setSource starts to convert this to an array.
     * If empty use getSource() for array version
     *
     * @return DataList
     */
    public function getSourceList()
    {
        return $this->sourceList;
    }

    /**
     * Set the model class name for tags
     *
     * @param DataList $sourceList
     * @return TagField
     */
    public function setSourceList($sourceList)
    {
        $this->sourceList = $sourceList;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function Field($properties = [])
    {
        $this->addExtraClass('entwine');

        return $this->customise($properties)->renderWith(TagField::class);
    }

    /**
     * Provide TagField data to the JSON schema for the frontend component
     *
     * @return array
     */
    public function getSchemaDataDefaults()
    {
        $options = $this->getOptions(true);
        $name = $this->getName();

        if ($this->getIsMultiple() && strpos($name, '[') === false) {
            $name .= '[]';
        }

        $schema = array_merge(
            parent::getSchemaDataDefaults(),
            [
                'name' => $name,
                'lazyLoad' => $this->getShouldLazyLoad(),
                'creatable' => $this->getCanCreate(),
                'multi' => $this->getIsMultiple(),
                'value' => $options->count() ? $options->toNestedArray() : null,
                'disabled' => $this->isDisabled() || $this->isReadonly(),
            ]
        );

        if (!$this->getShouldLazyLoad()) {
            $schema['options'] = array_values($this->getOptions()->toNestedArray() ?? []);
        } else {
            $schema['optionUrl'] = $this->getSuggestURL();
        }

        return $schema;
    }

    /**
     * @return string
     */
    protected function getSuggestURL()
    {
        return Controller::join_links($this->Link(), 'suggest');
    }

    /**
     * @return ArrayList<ArrayData>
     */
    protected function getOptions($onlySelected = false)
    {
        $options = ArrayList::create();
        $source = $this->getSourceList();

        // No source means we have no options
        if (!$source) {
            return ArrayList::create();
        }

        $dataClass = $source->dataClass();

        $values = $this->getValueArray();

        // If we have no values and we only want selected options we can bail here
        if (empty($values) && $onlySelected) {
            return ArrayList::create();
        }

        $titleField = $this->getTitleField();

        // Convert an array of values into a datalist of options
        if (!$values instanceof SS_List) {
            if (is_array($values) && !empty($values)) {
                // if values is an array of Ids then we should look up via
                // ID.
                if (array_filter($values, 'is_int')) {
                    $queryField = 'ID';
                } else {
                    $queryField = $titleField;
                }

                if (is_a($source, DataList::class)) {
                    $values = $source->filterAny([
                        $queryField => $values
                    ]);
                } else {
                    $values = DataList::create($dataClass)
                        ->filterAny([
                            $queryField => $values
                        ]);
                }
            } else {
                $values = ArrayList::create();
            }
        }

        // Prep a function to parse a dataobject into an option
        $addOption = function (DataObject $item) use ($options, $values, $titleField) {
            $option = $item->$titleField;

            $options->push(ArrayData::create([
                'Title' => $option,
                'Value' => $option,
                'Selected' => (bool) $values->find($titleField, $option)
            ]));
        };

        // Only parse the values if we only want the selected items in the values list (this is for lazy-loading)
        if ($onlySelected) {
            $values->each($addOption);
            return $options;
        }

        $source->each($addOption);

        return $options;
    }


    /**
     * Gets the source array if required
     *
     * Note: this is expensive for a SS_List
     *
     * @return array
     */
    public function getSource()
    {
        if (is_null($this->source)) {
            $this->source = $this->getListMap($this->getSourceList());
        }
        return $this->source;
    }


    /**
     * Intercept DataList source
     *
     * @param mixed $source
     * @return $this
     */
    public function setSource($source)
    {
        // When setting a datalist force internal list to null
        if ($source instanceof DataList) {
            $this->source = null;
            $this->setSourceList($source);
        } else {
            parent::setSource($source);
        }
        return $this;
    }


    /**
     * @param DataObject|DataObjectInterface $record DataObject to save data into
     * @throws Exception
     */
    public function getAttributes()
    {
        $name = $this->getName();

        if ($this->getIsMultiple() && strpos($name, '[') === false) {
            $name .= '[]';
        }

        return array_merge(
            parent::getAttributes(),
            [
                'name' => $name,
                'style' => 'width: 100%',
            ]
        );
    }


    protected function getListValues($values): array
    {
        if (empty($values)) {
            return [];
        }

        if (is_array($values)) {
            return $values;
        }

        if ($values instanceof SS_List) {
            return $values->column($this->getTitleField());
        }

        if ($values instanceof DataObject && $values->exists()) {
            return [$values->{$this->getTitleField()} ?? $values->ID];
        }

        if (is_int($values)) {
            return [$values];
        }

        return [trim((string) $values)];
    }


    /**
     * {@inheritdoc}
     */
    public function saveInto(DataObjectInterface $record)
    {
        $name = $this->getName();
        $values = $this->getValueArray();

        $ids = [];

        if (!$values) {
            $values = [];
        }

        if (empty($record)) {
            return;
        }

        /** @var Relation $relation */
        $relation = $record->hasMethod($name) ? $record->$name() : null;

        foreach ($values as $key => $value) {
            $tag = $this->getOrCreateTag($value);

            if ($tag) {
                $ids[] = $tag->ID;
                $values[$key] = $tag->Title;
            }
        }


        if ($relation instanceof Relation) {
            // Save ids into relation
            $relation->setByIDList(array_filter($ids ?? []));
        } elseif ($record->hasField($name)) {
            if ($this->getIsMultiple()) {
                if ($record->obj($name) instanceof DBMultiEnum) {
                    // Save dataValue into field... a CSV for DBMultiEnum
                    $record->$name = $this->csvEncode(array_filter(array_values($values)));
                } else {
                    // ... JSON-encoded string for other fields
                    $record->$name = $this->stringEncode(array_filter(array_values($values)));
                }
            } else {
                if (isset($tag) && $tag->ID) {
                    $record->$name = $tag->ID;
                } else {
                    $record->$name = null;
                }
            }
        }
    }

    /**
     * Get or create tag with the given value
     *
     * @param string $value
     *
     * @return DataObject|bool
     */
    protected function getOrCreateTag($value)
    {
        if (is_array($value)) {
            $value = $value['Value'] ?? '';
        }

        // Check if existing record can be found
        $source = $this->getSourceList();
        $titleField = $this->getTitleField();

        if (!$source) {
            return false;
        }

        $record = $source
            ->filter($titleField, $value)
            ->first();

        if ($record) {
            return $record;
        }

        // Create new instance if not yet saved
        if ($this->getCanCreate() && $value) {
            $dataClass = $source->dataClass();
            $record = Injector::inst()->create($dataClass);
            $record->{$titleField} = $value;
            $record->write();

            if ($source instanceof SS_List) {
                $source->add($record);
            }

            return $record;
        }

        return false;
    }

    /**
     * Returns a JSON string of tags, for lazy loading.
     *
     * @param  HTTPRequest $request
     * @return HTTPResponse
     */
    public function suggest(HTTPRequest $request)
    {
        $tags = $this->getTags($request->getVar('term'));

        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody(json_encode(['items' => $tags]));

        return $response;
    }

    /**
     * Returns array of arrays representing tags.
     *
     * @param  string $term
     * @return array
     */
    protected function getTags($term)
    {
        $source = $this->getSourceList();
        if (!$source) {
            return [];
        }

        $titleField = $this->getTitleField();

        $query = $source
            ->filter($titleField . ':PartialMatch:nocase', $term)
            ->sort($titleField)
            ->limit($this->getLazyLoadItemLimit());

        // Map into a distinct list
        $items = [];
        $titleField = $this->getTitleField();

        foreach ($query->map('ID', $titleField)->values() as $title) {
            $items[$title] = [
                'Title' => $title,
                'Value' => $title,
            ];
        }

        return array_values($items ?? []);
    }

    /**
     * Converts the field to a readonly variant.
     *
     * @return ReadonlyTagField
     */
    public function performReadonlyTransformation()
    {
        $copy = $this->castedCopy(ReadonlyTagField::class);
        $copy->setSourceList($this->getSourceList());
        $copy->setTitleField($this->getTitleField());

        return $copy;
    }

    /**
     * Prevent the default, which would return "tag"
     *
     * @return string
     */
    public function Type()
    {
        return '';
    }

    public function getSchemaStateDefaults()
    {
        $data = parent::getSchemaStateDefaults();

        // Add options to 'data'
        $data['lazyLoad'] = $this->getShouldLazyLoad();
        $data['multi'] = $this->getIsMultiple();
        $data['optionUrl'] = $this->getSuggestURL();
        $data['creatable'] = $this->getCanCreate();
        $options = $this->getOptions(true);
        $data['value'] = $options->count() ? $options->toNestedArray() : null;

        return $data;
    }


    public function getSchemaDataType(): string
    {
        if ($this->getIsMultiple()) {
            return TagField::SCHEMA_DATA_TYPE_MULTISELECT;
        }

        return TagField::SCHEMA_DATA_TYPE_SINGLESELECT;
    }
}
