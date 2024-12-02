<?php

namespace SilverStripe\TagField;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\MultiSelectField;
use SilverStripe\Forms\Validator;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\FieldType\DBMultiEnum;
use SilverStripe\ORM\Relation;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ArrayData;

/**
 * Provides a tagging interface, storing links between tag DataObjects and a parent DataObject.
 *
 * @package forms
 * @subpackage fields
 */
class TagField extends MultiSelectField
{
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
     * This is the field that populates the label displayed in the UI
     * It can be either a DB field or a model method name
     *
     * @var string
     */
    protected $titleField = 'Title';

    /**
     * This is the field that is used to store selected values
     * It has to be a DB field or null
     * Use null for the auto-detection
     *
     * @var string
     */
    protected $valueField = 'Title';

    /**
     * This is the field which drives the "suggest" action via text-based search
     * It has to be a DB field
     *
     * @var string
     */
    protected $searchField = 'Title';

    /**
     * This is the field which drives the order of results that appear in the "suggest" action via text-based search
     * It has to be a DB field or empty string
     * Use empty string to skip order customisation which will result in whatever order the source list is in
     *
     * @var string
     */
    protected $sortField = 'Title';

    /**
     * Allow Raw data to be stored on the matching DB field of the model
     * Use this to cover cases which don't require form level data serialisation
     * such as MultiValueField (symbiote/silverstripe-multivaluefield)
     *
     * @var bool
     */
    protected $allowRawValue = false;

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
     * @param string|null $valueField
     * @param string|null $searchField
     * @param string|null $sortField
     * @param bool $allowRawValue
     */
    public function __construct(
        $name,
        $title = '',
        $source = [],
        $value = null,
        $titleField = 'Title',
        $valueField = null,
        $searchField = null,
        $sortField = null,
        $allowRawValue = false
    ) {
        $this
            ->setTitleField($titleField)
            ->initValueField($valueField)
            ->initSearchField($searchField)
            ->initSortField($sortField)
            ->setAllowRawValue($allowRawValue);

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
     * @param string $valueField
     * @return $this
     */
    public function setValueField($valueField)
    {
        $this->valueField = $valueField;

        return $this;
    }

    /**
     * @return string
     */
    public function getValueField()
    {
        return $this->valueField;
    }

    /**
     * @param $searchField
     * @return $this
     */
    public function setSearchField($searchField)
    {
        $this->searchField = $searchField;

        return $this;
    }

    /**
     * @return string
     */
    public function getSearchField()
    {
        return $this->searchField;
    }

    /**
     * @param string $fieldName
     * @return $this
     */
    public function setSortField($fieldName)
    {
        $this->sortField = $fieldName;

        return $this;
    }

    /**
     * @return string
     */
    public function getSortField()
    {
        return $this->sortField;
    }

    /**
     * @param bool $allowRawValue
     * @return $this
     */
    public function setAllowRawValue($allowRawValue)
    {
        $this->allowRawValue = $allowRawValue;

        return $this;
    }

    /**
     * @return bool
     */
    public function getAllowRawValue()
    {
        return $this->allowRawValue;
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
        $valueField = $this->getValueField();

        // Convert an array of values into a datalist of options
        if (!$values instanceof SS_List) {
            if (is_array($values) && !empty($values)) {
                $values = is_a($source, DataList::class)
                    ? $source->filterAny([
                        $valueField => $values,
                    ])
                    : DataList::create($dataClass)
                        ->filterAny([
                            $valueField => $values,
                        ]);
            } else {
                $values = ArrayList::create();
            }
        }

        // Prep a function to parse a dataobject into an option
        $addOption = function (DataObject $item) use ($options, $values, $titleField, $valueField) {
            $title = $item->{$titleField};
            $value = $item->{$valueField};

            $options->push(ArrayData::create([
                'Title' => $title,
                'Value' => $value,
                'Selected' => (bool) $values->find($valueField, $value)
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
                'data-schema' => json_encode($this->getSchemaData()),
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
            return $values->column($this->getValueField());
        }

        if ($values instanceof DataObject && $values->exists()) {
            return [$values->{$this->getValueField()}];
        }

        if (is_int($values)) {
            return [$values];
        }

        return [trim((string) $values)];
    }

    /**
     * @param DataObjectInterface $record
     * @return void
     */
    public function loadFrom(DataObjectInterface $record): void
    {
        $fieldName = $this->getName();

        if (!$fieldName) {
            return;
        }

        if ($this->getAllowRawValue()) {
            // Load raw value without de-serialisation
            $this->value = $record->{$fieldName};

            return;
        }

        parent::loadFrom($record);
    }

    /**
     * {@inheritdoc}
     */
    public function saveInto(DataObjectInterface $record)
    {
        $fieldName = $this->getName();
        $values = $this->getValueArray();

        // We need to extract IDs as in some cases (Relation) we are unable to use the value field
        $ids = [];

        if (!$values) {
            $values = [];
        }

        if (empty($record)) {
            return;
        }

        $valueField = $this->getValueField();
        $tag = null;
        $cleanValues = [];

        foreach ($values as $value) {
            $tag = $this->getOrCreateTag($value);

            if (!$tag) {
                continue;
            }

            $ids[] = $tag->ID;
            $cleanValues[] = $tag->{$valueField};
        }

        /** @var Relation $relation */
        $relation = $record->hasMethod($fieldName)
            ? $record->$fieldName()
            : null;

        if ($relation instanceof Relation) {
            // Save values into relation
            $relation->setByIDList(array_filter($ids ?? []));
        } elseif ($this->getAllowRawValue()) {
            // Store raw data without serialisation
            $record->{$fieldName} = $cleanValues;
        } elseif ($record->hasField($fieldName)) {
            if ($this->getIsMultiple()) {
                $record->{$fieldName} = $record->obj($fieldName) instanceof DBMultiEnum
                    // Save dataValue into field... a CSV for DBMultiEnum
                    ? $this->csvEncode(array_filter(array_values($cleanValues)))
                    // ... JSON-encoded string for other fields
                    : $this->stringEncode(array_filter(array_values($cleanValues)));
            } else {
                // Detect has one as this case needs ID as opposed to custom value
                $relations = $record->hasOne();
                $hasOneDetected = false;

                foreach ($relations as $relationName => $relationTarget) {
                    $foreignKey = $relationName . 'ID';

                    if ($foreignKey === $fieldName) {
                        $hasOneDetected = true;

                        break;
                    }
                }

                $targetField = $hasOneDetected ? 'ID' : $valueField;
                $record->{$fieldName} = $tag && $tag->{$targetField}
                    ? $tag->{$targetField}
                    : null;
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
        $valueField = $this->getValueField();

        if (!$source) {
            return false;
        }

        $record = $source
            ->filter($valueField, $value)
            ->first();

        if ($record) {
            return $record;
        }

        // Create new instance if not yet saved
        if ($this->getCanCreate() && $value) {
            $dataClass = $source->dataClass();
            $record = Injector::inst()->create($dataClass);
            $record->{$valueField} = $value;
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
        $valueField = $this->getValueField();
        $searchField = $this->getSearchField();
        $sortField = $this->getSortField();

        $list = $source
            ->filter($searchField . ':PartialMatch:nocase', $term)
            ->limit($this->getLazyLoadItemLimit());

        // Optionally apply sort
        if ($sortField) {
            $list = $list->sort($searchField);
        }

        // Map into a distinct list
        $items = [];

        foreach ($list as $record) {
            $value = $record->{$valueField};
            $items[$value] = [
                'Title' => $record->{$titleField},
                'Value' => $value,
            ];
        }

        return array_values($items ?? []);
    }

    /**
     * DropdownField assumes value will be a scalar so we must
     * override validate. This only applies to Silverstripe 3.2+
     *
     * @param Validator $validator
     * @return bool
     */
    public function validate($validator)
    {
        return $this->extendValidationResult(true, $validator);
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

    /**
     * Provide a good default for value field
     *
     * @param string|null $value
     * @return $this
     */
    protected function initValueField($value)
    {
        $value = $value ?? $this->getTitleField();

        return $this->setValueField($value);
    }

    /**
     * Provide a good default for search field
     *
     * @param string|null $value
     * @return $this
     */
    protected function initSearchField($value)
    {
        $value = $value ?? $this->getTitleField();

        return $this->setSearchField($value);
    }

    /**
     * Provide a good default for sort field
     *
     * @param string|null $value
     * @return $this
     */
    protected function initSortField($value)
    {
        $value = $value ?? $this->getSearchField();

        return $this->setSortField($value);
    }
}
