<?php

namespace Understory\ACF;

use Understory\MetaDataBinding;
use Understory\Registerable;

/**
 * Understory wrapper for ACF's Options Page functionality. See:
 * https://www.advancedcustomfields.com/resources/acf_add_options_page/
 * for more information including all the configuation options along
 * with their defaults.
 *
 * `page_title` is automatically generated by the $title value passed in
 * to the constructor.
 *
 * 'post_id' and 'menu_slug' are automatically generated by the $title value
 * passed in to the constructor, made lowercase with dashes.
 */
class OptionsPage implements MetaDataBinding, Registerable
{
    private $title;

    private $id;

    private $config = [];

    /**
     * Pass in the title of the Options Page along with any custom config
     *
     * @param string $title  Options Page Title
     * @param array $config  (optional) Options Page Configuration
     */
    public function __construct($title, $config = [])
    {
        $this->setTitle($title);
        $this->setId(str_replace(' ', '-', strtolower($title)));
        $this->setConfig($config);
    }

    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Used internally to set the title and 'page_title' config
     *
     * @param string $title
     */
    private function setTitle($title)
    {
        $this->title = $title;
        $this->setConfig([
            'page_title' => $title,
        ]);
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the `post_id` and `menu_slug` config optionsPage
     *
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
        $this->setConfig([
            'post_id' => $id,
            'menu_slug' => $id
        ]);
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Merges new config values with existing config values
     *
     * @param array $config     New config values
     * @return $this
     */
    public function setConfig($config)
    {
        $this->config = array_merge($this->getConfig(), $config);

        return $this;
    }

    /**
     * Registers the options page with wordpress using ACF's build in function.
     * Call only *once* per OptionsPage type.
     */
    public function register()
    {
        // if (function_exists('acf_add_options_page')) {
        $page = acf_add_options_page($this->config);
        // }
    }

    /**
     * Implentation of \UnderStory\MetaDataBinding::getMetaValue
     *
     * @param  string $metaFieldKey  Key for the meta field
     * @return string                Value of the meta field
     */
    public function getMetaValue($key)
    {
        return $this->getOption($key);
    }

    /**
     * Implentation of \UnderStory\MetaDataBinding::setMetaValue
     *
     * @param  string $key          Key for the meta field
     * @param  string $value        Value for the meta field
     */
    public function setMetaValue($key, $value)
    {
        $this->setOption($key, $value);
    }

    /**
     * Wrap WordPress's built in `get_option` method passing the option name
     * combined with the `id` of the OptionsPage. This is how ACF stores the
     * options in the wp_options table.
     *
     * @param  string $optionName
     * @return string
     */
    private function getOption($optionName)
    {
        return get_option($this->getId().'_'.$optionName);
    }

    /**
     * Wrap WordPress's built in `set_option` method passing the option name'
     * combined with the `id` of the OptionsPage. This is how ACF stores the
     * options in the wp_options table.
     *
     * @param  string $optionName
     * @param  string $value
     * @return bool False if value was not updated and true if value was updated.
     */
    private function setOption($optionName, $value)
    {
        return update_option($this->getId().'_'.$optionName, $value);
    }

    public function getBindingName()
    {
        return $this->getId();
    }
}
