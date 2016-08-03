<?php

namespace Understory\ACF;

use Understory\DelegatesMetaDataBinding;
use Understory\MetaDataBinding;
use Understory\Registerable;
use Understory;

abstract class FieldGroup implements DelegatesMetaDataBinding, Registerable
{
    use \Understory\Core;

    /**
     * MetaDataBinding of the Object that has this field.
     * This can be a CustomPostType, CustomTaxonomy, Options Page, or View
     * or it can be an object that implements FieldGroupInterface.
     *
     * @var mixed
     */
    private $metaDataBinding;

    /**
     * Parent Field Group that gets passed to the constructor. We will use this
     * to generate the fully qualified namespace for meta field keys.
     *
     * @var FieldGroup
     */
    private $parentFieldGroup;

    /**
     * If this field is part of a repeater or flexible content field, this is the prefix
     * of the meta key in the database meta tables, for a particular row for a particular field.
     *
     * @var string
     */
    private $metaValueNamespace = '';

    private $namespace = '';

    /**
     * Cached array of Custom Field Groups in a repeater.
     *
     * @var array
     */
    private $repeaterRows = [];

    protected $config = null;
    /**
     * Pass in the metaDataBinding of the Object that has this field.
     *
     * @param mixed  $metaDataBinding              gets passed to registerRule
     * @param string $metaValueNamespace if part of a repeater pass in the prefix to retrive
     *                                   the meta data from the database for that row
     */
    public function __construct(MetaDataBinding $binding = null, $metaValueNamespace = '')
    {
        $this->setMetaValueNamespace($metaValueNamespace);
        if (is_a($binding, self::class)) {
            $this->setParentFieldGroup($binding);
            // Get metaDataBinding from ParentFieldGroup
            $this->setMetaDataBinding($this->getParentFieldGroup()->getMetaDataBinding());
        } else if ($binding) {
            $this->setMetaDataBinding($binding);
        }

    }

    /**
     * Initalize a FieldsBuilder with the class's name as the group name
     * @return FieldsBuilder
     */
    private function initializeBuilder()
    {
        $reflectionClass = new \ReflectionClass(static::class);

        // Chop off the namespace
        $className = $reflectionClass->getShortName();

        // Convert to snake case
        $className = ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $className)), '_');

        return new FieldsBuilder($className);
    }

    /**
     * Returns the AcfBuilder object. If config isn't set, set it to the
     * defaultConfig
     * @return \StoutLogic\acf-builder\FieldsBuilder
     */
    public function getConfig()
    {
        if (!$this->config) {
            // Retrive the default config. Create a new builder and pass to
            // configure method.
            $config = $this->configure($this->initializeBuilder());
            $config = $config->getRootContext();
            $this->setConfig($config);
        }

        return $this->config;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function setGroupConfig($key, $value)
    {
        $this->getConfig()->setGroupConfig($key, $value);
        return $this;
    }

    public function hideOnScreen($value)
    {
        $hide = $this->getConfig()->getGroupConfig('hide_on_screen') || [];
        $hide[] = $value;
        $this->getConfig()->setGroupConfig('hide_on_screen', $hide);
        return $this;
    }

    public function hideContentEditor()
    {
        $this->hideOnScreen('the_content');
        return $this;
    }

    /**
     * @param  FieldsBuilder $builder to configure
     * @return FieldsBuilder
     */
    protected function configure($builder)
    {
        return $builder;
    }

    /**
     * Call and pass in the object's metaDataBinding, usually a class or instance, also
     * allows you to pass in any custom ACF field group configuartion that will
     * get merged into the field groups default Configuration.
     *
     * @param mixed $metaDataBinding        Object that will have this field, or ACF rule Array
     * @return $this  to chain together multiple locations or a register method call
     */
    private function setLocationForMetaDataBinding($metaDataBinding)
    {
        if ($metaDataBinding instanceof Understory\View) {
            $this->setViewLocation($metaDataBinding);
        } else if ($metaDataBinding instanceof Understory\CustomPostType) {
            $this->setCustomPostTypeLocation($metaDataBinding);
        } else if ($metaDataBinding instanceof Understory\CustomTaxonomy) {
            $this->setCustomTaxonomyLocation($metaDataBinding);
        } else if ($metaDataBinding instanceof Understory\ACF\OptionPage) {
            $this->setOptionsPageLocation($metaDataBinding);
        } else if ($metaDataBinding instanceof Understory\User) {
            $this->setUserFormLocation($metaDataBinding);
        }
        return $this;
    }

    /**
     * Sets the location of the FieldGroup.
     * If location data already exists on the builder, the new condition will
     * be added as and `and` condition.
     * @param string $param
     * @param string $operator
     * @param string $value
     * @return \StoutLogic\AcfBuilder\LocationBuilder
     */
    public function setLocation($param, $operator, $value)
    {
        $builder = $this->getConfig();

        if (null === $builder->getLocation()) {
            return $builder->setLocation($param, $operator, $value);
        }

        return $builder->getLocation()->and($param, $operator, $value);
    }

    private function setViewLocation(Understory\View $metaDataBinding)
    {
        $fileName = $metaDataBinding->getFileName();
        $viewFile = 'app/Views'.$fileName.'.php';
        $this->namespaceFieldGroupKey('view_' . str_replace('/', '', $fileName));

        // Check to see if this is the default template
        if (in_array(Understory\DefaultPage::class, class_implements($metaDataBinding))) {
            $viewFile = 'default';
        }

        $this->setLocation('page_template', '==', $viewFile);
    }

    private function setCustomPostTypeLocation(Understory\CustomPostType $metaDataBinding)
    {
        $postType = $metaDataBinding->getPostType();
        $this->namespaceFieldGroupKey('post_type_' . $postType);

        $this->setLocation('post_type', '==', $postType);
    }

    private function setCustomTaxonomyLocation(Understory\CustomTaxonomy $metaDataBinding)
    {
        $taxonomy = $metaDataBinding->getName();
        $this->namespaceFieldGroupKey('post_type_' . $taxonomy);

        $this->setLocation('taxonomy', '==', $taxonomy);
    }

    private function setOptionsPageLocation(Understory\ACF\OptionPage $metaDataBinding)
    {
        $this->namespaceFieldGroupKey('options_' . $metaDataBinding->getId());
        $this->setLocation('options_page', '==', $metaDataBinding->getId());
    }

    private function setUserFormLocation(Understory\User $metaDataBinding)
    {
        $this->namespaceFieldGroupKey('user');
        $this->setLocation('user_form', '==', 'all');
    }

    private function namespaceFieldGroupKey($append)
    {
        $builder = $this->getConfig();
        $builder->setGroupConfig('key',
            $builder->getGroupConfig('key') . '_' . $append
        );
    }

    /**
     * Register a fieldGroup with set locations.
     *
     * @param object $metaDataBinding     Shortcut which will call setLocation with
     *                          this metaDataBinding.
     * @param integer $order    Order the field group should appear in the metaDataBinding
     * @return array $config    The final ACF config array
     */
    public function register($metaDataBinding = null, $order = 0)
    {
        if (!$metaDataBinding) {
            $metaDataBinding = $this->getMetaDataBinding();
        }
        $this->setLocationForMetaDataBinding($metaDataBinding);

        $builder = $this->getConfig();
        $builder->setGroupConfig('menu_order', $order);

        // Namespace the config keys
        $config = $builder->build();


        // Optimization:
        // Don't register a field group that already exists
        // if (!acf_is_local_field_group($config['key']))
        if (function_exists('acf_add_local_field_group')) {
            acf_add_local_field_group($config);
        }

        return $config;
    }

    /**
     * Create and retreive a value of our field as part of a repeater.
     * We will create an intermediary FieldGroup that is properly namespaced.
     *
     * @param string $metaFieldKey field name
     * @param int    $index
     *
     * @return FieldGroup
     */
    private function getRepeaterRow($metaFieldKey, $index)
    {
        if (!isset($this->repeaterRows[$index])) {
            // Determine Namespace
            $namespace = $metaFieldKey.'_'.$index;

            // Create a new instance of our current FieldGroup sub class
            $fieldGroupClass = get_called_class();
            $this->repeaterRows[$index] = new $fieldGroupClass($this,  $namespace);
        }

        return $this->repeaterRows[$index];
    }

    /**
     * Calls getMetaValue on the metaDataBinding, that is properly namespaced so that it
     * works with repeaters and flexible post types.
     *
     * @param string         $metaFieldKey
     * @param int (optional) $index
     *
     * @return mixed Meta Value or FieldGroup
     */
    public function getMetaValue($metaFieldKey, $index = null)
    {
        if (isset($index)) {
            return $this->getRepeaterRow($metaFieldKey, $index);
        }

        $namespacedMetaFieldKey = $this->getNamespacedMetaFieldKey($metaFieldKey);

        return $this->getMetaDataBinding()->getMetaValue($namespacedMetaFieldKey);
    }

    public function setMetaValue($metaFieldKey, $value)
    {
        $namespacedMetaFieldKey = $this->getNamespacedMetaFieldKey($metaFieldKey);
        $this->getMetaDataBinding()->setMetaValue($namespacedMetaFieldKey, $value);
    }

    /**
     * The meta shortcut function is an alias of getMetaValue.
     *
     * It is important to note that the meta function is used by Timber and
     * Understory when attempting to do a method_missing __get lookup.
     * This allows one to simply call $this->name from php or a twig file instead
     * of defining a getName function to manually return the metaValue.
     *
     * A getter is still required if the value needs any post processing
     *
     * @param string $metaFieldKey
     * @param index  $index        optional
     *
     * @return mixed Meta Value or FieldGroup
     */
    public function meta($metaFieldKey, $index = null)
    {
        return $this->getMetaValue($metaFieldKey, $index);
    }

    /**
     * Memoized values returned from getMetaValues.
     *
     * @var array
     */
    private $metaValues = [];

    /**
     * Return an array of meta values, contained in a repeater field
     * or flexible content field.
     * Optionally instatiate each value as a FieldGroup subclass if a
     * repeater field
     *
     * @param string           $metaFieldKey
     * @param FieldGroup $className    (optional) class to instatiate value as
     *
     * @return array
     */
    public function getMetaValues($metaFieldKey, $className = null)
    {
        if (!array_key_exists($metaFieldKey, $this->metaValues)) {
            $this->metaValues[$metaFieldKey] = [];


            $value = $this->getMetaValue($metaFieldKey);

            // Check to see if is a repeater or flexible content field
            if (is_numeric($value)) {
                // Repeater
                $count = $value;
            } else {
                // Flexible Content
                $classNames = unserialize($value);
                $count = count($classNames);
            }

            for ($i = 0; $i < $count; $i++) {
                if (isset($classNames) && is_array($classNames)) {
                    $className = $classNames[$i];
                }

                if (class_exists($className)) {
                    $this->metaValues[$metaFieldKey][] = new $className($this->getMetaValue($metaFieldKey, $i));
                } else {
                    $this->metaValues[$metaFieldKey][] = $this->getMetaValue($metaFieldKey, $i);
                }
            }
        }

        return $this->metaValues[$metaFieldKey];
    }

    /**
     * Recursively determine our Parent Field Group's metaValueNamespace.
     *
     * @return string parent meta value namespace
     */
    protected function getParentMetaValueNamespace()
    {
        if ($this->getParentFieldGroup() && $this->getParentFieldGroup()->getMetaValueNamespace() !== '') {
            $parentNamespace =
                $this->getParentFieldGroup()->getParentMetaValueNamespace().
                $this->getParentFieldGroup()->getMetaValueNamespace();

            if ($parentNamespace !== '') {
                $namespace = $parentNamespace.'_';

                return $namespace;
            }
        }

        return '';
    }

    /**
     * Combine the metaFieldKey with our current namespace.
     *
     * @param [type] $metaFieldKey [description]
     *
     * @return [type] [description]
     */
    protected function getNamespacedMetaFieldKey($metaFieldKey)
    {
        $namespace = $this->getParentMetaValueNamespace().$this->getMetaValueNamespace();

        if ($namespace !== '') {
            // Ensure only one _ appears after the namespace
            $namespace = rtrim($namespace, '_');
            $namespace .= '_';
        }

        return $namespace.$metaFieldKey;
    }

    /**
     * Getters / Setters.
     */
    public function setMetaDataBinding(MetaDataBinding $metaDataBinding)
    {
        $this->metaDataBinding = $metaDataBinding;
    }

    public function getMetaDataBinding()
    {
        return $this->metaDataBinding;
    }

    private function setParentFieldGroup($parentFieldGroup)
    {
        $this->parentFieldGroup = $parentFieldGroup;
    }

    private function getParentFieldGroup()
    {
        return $this->parentFieldGroup;
    }

    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    public function getNamespace($namespace)
    {
        return $this->namespace;
    }

    private function setMetaValueNamespace($metaValueNamespace)
    {
        $this->metaValueNamespace = $metaValueNamespace;
    }

    private function getMetaValueNamespace()
    {
        return $this->metaValueNamespace;
    }
}