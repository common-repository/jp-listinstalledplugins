<?php

/**
 * Plugin Name: List Installed Plugins
 * Version: 1.5.1
 * Plugin URI: http://jp.jixor.com/plugins/list-installed-plugins
 * Author: Stephen Ingram
 * Author URI: http://jp.jixor.com
 * Description: Lists your currently installed and activated plugins. Plugins can be hidden form the list if desired. Options page allows you to set a page to display the list on, change the format, and configure other options. The plugin also features a customizable widget to display your plugins. The plugin is multi-lingual ready with much text already being translated using built in strings. To summarize; this plugin features as many customizations as you could possibly want for something seemingly so simple.
 *
 * @category  Plugin
 * @package   jp-listinstalledplugins
 * @author    Stephen Ingram <code@jixor.com>
 * @copyright Copyright (c) 2009, Stephen Ingram
 * @todo      Cache the widget's output
 * @todo      Add integrated help
 */

/**
 * The List Installed Plugins Class
 */
class jp_lip
{

    /**
     * @var string
     */
    var $plugin_folder;

    /**
     * @var string
     */
    var $plugin_basename;

    /**
     * @var array
     */
    var $messages;

    /**
     * @var array
     */
    var $options;

    /**
     * Stores plguin details
     *
     * @var array
     */
    var $plugins;

    /**
     * Stores output, standard/xsl
     *
     * @var string
     */
    var $out;



    /**
     * PHP 4 style construcotr.
     */
    function jp_lip()
    {

        $this->messages = array();

        $this->plugin_folder   = dirname(__FILE__);
        $this->plugin_basename = plugin_basename(__FILE__);

        $this->get_options();

        if (strpos($_SERVER['REQUEST_URI'], 'plugins.php'))
            $this->do_actions();

        add_filter('plugin_action_links', array($this, 'filter_plugin_action_links'), 1, 4);
        add_filter("plugin_action_links_{$this->plugin_basename}", array($this, 'filter_plugin_action')); 
        add_action('after_plugin_row', array($this, 'action_after_plugin_row'), 10, 4);
        add_action('admin_menu', array($this, 'action_admin_menu'));

        if ($this->options['template_enable'])
            add_filter('the_content', array($this, 'filter_the_content'));

        if ($this->options['post_id'])
            add_filter('the_content', array($this, 'filter_the_content_replace'));

        if ($this->options['widget_enable'])
            add_action('widgets_init', array($this, 'action_widgets_init'));

    }



    /**
     * Sets self::options while filling with defaults if required.
     *
     * @return void
     */
    function get_options()
    {

        $this->options = get_option('jp_lip');

        $update = false;

        if (!is_array($this->options))
            $this->options = array();

        if (!isset($this->options['hidden_plugins']))
        {
            $this->options['hidden_plugins'] = array();
            $update = true;
        }

        if (!isset($this->options['required_capability'])
            || empty($this->options['required_capability'])
            )
        {
            $this->options['required_capability'] = 'switch_themes';
            $update = true;
        }

        if (!isset($this->options['post_id']))
        {
            $this->options['post_id'] = null;
            $update = true;
        }

        if (!isset($this->options['caption']))
        {
            $this->options['caption'] = '';
            $update = true;
        }

        if (!isset($this->options['use_xsl'])) {
            $this->options['use_xsl'] = false;
            $update = true;
        }

        if (!isset($this->options['xsl'])
            || empty($this->options['xsl'])
            )
        {
            $this->options['xsl'] = file_get_contents($this->plugin_folder . DIRECTORY_SEPARATOR . 'template.xslt');
            $updated = true;
        }

        if (!isset($this->options['template_enable']))
        {
            $this->options['template_enable'] = false;
            $updated = true;
        }

        if (!isset($this->options['widget_enable']))
        {
            $this->options['widget_enable'] = true;
            $updated = true;
        }

        if (!isset($this->options['widget_title']))
        {
            $this->options['widget_title'] = '';
            $updated = true;
        }

        if (!isset($this->options['widget_xsl'])
            || empty($this->options['widget_xsl'])
            )
        {
            $this->options['widget_xsl'] = file_get_contents($this->plugin_folder . DIRECTORY_SEPARATOR . 'widget.xslt');
            $updated = true;
        }

        if (!isset($this->options['show_stats'])
            || !in_array($this->options['show_stats'], array('none', 'lite', 'full'))
            )
        {
            $this->options['show_stats'] = 'none';
            $updated = true;
        }



        if ($updated)
            update_option('jp_lip', $this->options);

        return;

    }



    /**
     * This method is called when it appears that show/hide actions have been
     * made on the plugins.php page.
     */
    function do_actions()
    {

        $message = '';

        if (!isset($_GET['jp_lip'])
            || !isset($_GET['jp_lip']['action'])
            || !isset($_GET['jp_lip']['plugin'])
            )
            return;

        if ($_GET['jp_lip']['action'] == 'show') {
            if(isset($this->options['hidden_plugins'][(string)$_GET['jp_lip']['plugin']]))
                unset($this->options['hidden_plugins'][(string)$_GET['jp_lip']['plugin']]);
        } else {
            $this->options['hidden_plugins'][(string)$_GET['jp_lip']['plugin']] = true;
            if (strpos((string)$_GET['jp_lip']['plugin'], 'listinstalledplugins'))
                $message = ' <strong>Please consider showing this plugin.</strong>';
        }

        $this->messages[(string)$_GET['jp_lip']['plugin']] = 'List Installed Plugins &rsaquo; ' . __('Changes saved.') . $message;

        update_option('jp_lip', $this->options);

        return;

    }



    function action_admin_menu()
    {

        add_submenu_page(
            'plugins.php',
            'List Installed Plugins &rsaquo; ' . __('Options'),
            'LIP ' . __('Options'),
            $this->options['required_capability'],
            'jp-listinstalledplugins.php',
            array(&$this, 'page_options')
            );

    }



    function action_widgets_init()
    {

        register_sidebar_widget('List Installed Plugins', array($this, 'widget_widget'));
        register_widget_control('List Installed Plugins', array($this, 'widget_control'));

    }



    function page_options()
    {

        $xsl = true;

        /**
         * Check for DOM library
         */
        if (!class_exists('DOMDocument'))
        {

            $xsl = false;
            $this->options['use_xsl'] = false;
            echo '<div class="error"><p>Your PHP enviroment must have the
                <strong>DOM extention</strong> installed to use XSL mode.
                Please contact your server\'s administrator for help with this
                issue.</p>
                </div>';

        }

        /**
         * Check for xslt library
         */
        if (!class_exists('XSLTProcessor'))
        {

            $xsl = false;
            $this->options['use_xsl'] = false;
            echo '<div class="error"><p>Your PHP enviroment must have the
                <strong>XSL extention</strong> installed to use XSL mode.
                Please contact your server\'s administrator for help with this
                issue.</p>
                </div>';

        }

        if ($_POST)
        {

            /**
             * Checkboxes
             */
            $this->options['template_enable'] = isset($_POST['template_enable']);
            $this->options['widget_enable'] = isset($_POST['widget_enable']);

            /**
             * Radiosets
             */
            $this->options['use_xsl'] = ($_POST['use_xsl'] == 'true'
                ? true
                : false
                );

            $this->options['required_capability'] = stripslashes($_POST['required_capability']);
            $this->options['caption'] = stripslashes($_POST['caption']);
            $this->options['xsl'] = stripslashes($_POST['xsl']);
            $this->options['widget_title'] = stripslashes($_POST['widget_title']);
            $this->options['widget_xsl'] = stripslashes($_POST['widget_xsl']);

            $this->options['post_id'] = (int)$_POST['post_id'];

            $this->options['show_stats'] = stripslashes($_POST['show_stats']);

            update_option('jp_lip', $this->options);

            echo '<div id="message" class="updated fade"><p>' . __('Settings saved.') . '</p></div>';

        }

        echo '<div class="wrap"><div id="icon-plugins" class="icon32"><br /></div>
<h2>List Installed Plugins &rsaquo; ' . __('Options') . '</h2>
            <form action="plugins.php?page=jp-listinstalledplugins.php"
                method="post"
                enctype="multipart/form-data">

<table class="form-table"><tbody>
    <tr>
        <th scope="row">
            <label for="form-required_capability">' . __('User Capability', 'jplip') . '</label>
        </th>
        <td>
            <input name="required_capability" id="form-required_capability" value="' . $this->options['required_capability'] . '" class="regular-text" />
            <span class="setting-description">' . __('Required user capability to change these settings. Clear to reset.', 'jplip') . '</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="form-post_id">' . __('Select page') . '</label></th>
        <td><select id="form-post_id" name="post_id">
            <option'
            . (!$this->options['post_id']
                ? ' selected="selected"'
                : ''
                )
            . ' value="">' . __('None') . '</option>';

        foreach (get_pages() as $page)
            echo '<option value="' . $page->ID . '"'
                . ($page->ID == $this->options['post_id']
                    ? ' selected="selected"'
                    : ''
                    )
                . '>' . $page->post_title . '</option>';

        echo '</select>
            <span class="setting-description">' . __('This option will replace the selected page\'s content with the installed plugins list. This is the prefered way of displaying the full list.', 'jplip')
            . '</span></td>
    </tr>

    <tr>
        <th scope="row">
            <label for="form-caption">' . __('Caption', 'jplip') . '</label>
        </th>
        <td>
            <input name="caption" id="form-caption" style="width:90%;"  value="' . $this->options['caption'] . '" class="regular-text" />
            <br /><span class="setting-description">' . __('Displayed above list for replacement and template tag modes only.', 'jplip') . '</span>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="form-caption">' . __('Show Stats', 'jplip') . '</label>
        </th>
        <td>
            <select id="form-show_stats" name="show_stats">
                <option value="none">None</option>
                <option value="lite">Active plugins</option>
                <option value="full">Total plugins, active plugins and inactive plugins</option>
            </select>
            <br /><span class="setting-description">' . __('Displayed above list for replacement and template tag modes only.', 'jplip') . '</span>
        </td>
    </tr>

    <tr>
        <th scope="row">' . __('Use XSL?', 'jplip') . '</th>
        <td>
            <fieldset><legend class="hidden">' . __('Use XSL?', 'jplip') . '</legend>
                
                    <label><input id="form-use_xsl-false" type="radio" name="use_xsl" value="false"'
            . (!$this->options['use_xsl']
                ? ' checked="checked"'
                : ''
                )
            . '/>
                    ' . __('Do not template output', 'jplip') . '</label>
                    <span class="setting-description">' . __('Microseconds quicker, but not customizable.', 'jplip') . '</span>
                
                
                    <br /><label><input id="form-use_xsl-true" type="radio" name="use_xsl" value="true"'
            . ($this->options['use_xsl']
                ? ' checked="checked"'
                : ''
                )
            . (!$xsl
                ? ' disabled="disabled"'
                : ''
                )
            . '/>
                    ' . __('Use XSL mode for output', 'jplip') . '</label>
                    <span class="setting-description">' . __('Microseconds slower, but fully customizable.', 'jplip') . '</span>
                
            </fieldset>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="form-xsl">' . __('XSL Template', 'jplip') . '</label></th>
        <td><textarea rows="15" cols="50" name="xsl" id="form-xsl" class="large-text code"'
            . (!$xsl
                ? ' readonly="readonly"'
                : ''
                )
            . '>'
            . htmlentities($this->options['xsl'])
            . '</textarea>
                <br /><span class="setting-description">' . __('Clear to reset to default template. Use the $version variable for multi-lingual version word. Note the &lt;byline&gt; element is also multi-lingual. Ensure when selecting &lt;title&gt; and &lt;byline&gt; elements you have disable-output-escaping set to &quot;yes&quot;.', 'jplip')
            . '</span></td>
    </tr>
    <tr>
        <th></th>
        <td><label><input type="checkbox" '
            . ($this->options['template_enable']
                ? ' checked="checked"'
                : ''
                )
            . ' name="template_enable" id="form-template_enable" value="true" />
                ' . __('Enable template tag?', 'jplip') . '</label>
                <span class="setting-description">' . __('Template tag enables you to include the plugin list in any post or page with a simple tag, however this slows down your site a tiny bit as every time a post or page is displayed it must be filtered by this plugin. To include the plugin list use the following tag: <code>&lt;!--#list-installed-plugins--&gt;</code>', 'jplip')
            . '</span></td>
    </tr>
</tbody></table>
<h3>' . __('Widget Options', 'jplip') . '</h3>
<table class="form-table"><tbody>
    <tr>
        <th></th>
        <td><label><input type="checkbox" '
            . ($this->options['widget_enable']
                ? ' checked="checked"'
                : ''
                )
            . ' name="widget_enable" id="form-widget_enable" /> ' . __('Enable Widget?', 'jplip') . '</label></td>
    </tr>
    <tr>
        <th scope="row"><label for="form-widget_title">' . __('Widget\'s Title', 'jplip') . '</label></th>
        <td><input type="text" name="widget_title" id="form-widget_title" class="regular-text"
                value="' . $this->options['widget_title'] . '" />
            <span class="setting-description">' . sprintf(__('Leave blank for default, &quot;%s&quot;, which is multi-lingual. Also editable via widget panel.', 'jplip'), __('Currently Active Plugins'))
            . '</span></td>
    </tr>
    <tr>
        <th scope="row"><label for="form-widget_xsl">' . __('Widget\'s XSL Template', 'jplip') . '</label></th>
        <td><textarea rows="15" cols="50" name="widget_xsl" id="form-widget_xsl" class="large-text code"'
            . (!$xsl
                ? ' readonly="readonly"'
                : ''
                )
            . '>'
            . htmlentities($this->options['widget_xsl'])
            . '</textarea>
                <br /><span class="setting-description">' . __('Clear to reset to default template. Use the $version variable for multi-lingual version word. Note the &lt;byline&gt; element is also multi-lingual. Ensure when selecting &lt;title&gt; and &lt;byline&gt; elements you have disable-output-escaping set to &quot;yes&quot;.', 'jplip')
            . '</span></td>
</tbody></table>
<p class="submit">
	<input type="submit" name="submit" class="button-primary" value="' . __('Save Changes') . '" />
</p>
                </form></div>';

    }



    function get_plugins()
    {

        if ($this->plugins)
            return;

        require_once(ABSPATH.'/wp-admin/admin-functions.php');

        $this->plugins = get_plugins();

        return;

    }



    function get_plugins_xml($active_plugins)
    {

        if ($this->xml)
            return;

        $this->get_plugins();

        $this->xml = new DOMDocument;

        $xml = '<plugins>';

        foreach($active_plugins as $name)
            if (!isset($this->options['hidden_plugins'][$name]))
                $xml .= '<plugin>
                        <file>' . $name . '</file>
                        <name>' . $this->plugins[$name]['Name'] . '</name>
                        <title>' . htmlentities($this->plugins[$name]['Title']) . '</title>
                        <pluginuri>' . $this->plugins[$name]['PluginURI'] . '</pluginuri>
                        <description>' . htmlentities($this->plugins[$name]['Description']) . '</description>
                        <version>' . $this->plugins[$name]['Version'] . '</version>
                        <author>' . htmlentities($this->plugins[$name]['Author']) . '</author>
                        <authoruri>' . $this->plugins[$name]['AuthorURI'] . '</authoruri>
                        <byline>' . htmlentities(sprintf(__('By %s'), $this->plugins[$name]['Author'])) . '</byline>
                    </plugin>';

        $this->xml->loadXML($xml . '</plugins>');

        return;

    }



    /**
     * Generates plugin list for replacement and template tag modes.
     *
     * Output stored to self::out
     *
     * @return void
     */
    function get_the_list()
    {

        if ($this->out)
            return;

        $active_plugins = get_settings('active_plugins');

        /**
         * Re-use language string defined for plugins management.
         */
        if (!$active_plugins) {

            $this->out = __('No plugins to show');
            return;

        } else {

            /**
             * Just incase all active plugins have been hidden.
             */
            $active = false;
            foreach($active_plugins as $name)
                if (!isset($this->options['hidden_plugins'][$name]))
                    $active = true;
            if (!$active) {
                $this->out = __('No plugins to show');
                return;
            }

        }

        if ($this->options['use_xsl']) {

            $this->get_plugins_xml($active_plugins);

            $xsl = new DOMDocument;
            $xsl->loadXML($this->options['xsl']);

            $proc = new XSLTProcessor;

            $proc->setParameter(
                '',
                array(
                    'version' => __('Version'),
                    'caption' => $this->options['caption'],
                    )
                );

            $proc->importStylesheet($xsl);

            $this->out = $proc->transformToXML($this->xml);

        } else {

            $this->get_plugins();

            $this->out = '<dl>';

            foreach($active_plugins as $name)
                $this->out .= (isset($this->options['hidden_plugins'][$name])
                    ? ''
                    : '
    <dt><a href="' . $this->plugins[$name]['PluginURI'] . '">' . $this->plugins[$name]['Title'] . '</a></dt>
        <dd>' . __('Version') . ': '
                        . $this->plugins[$name]['Version'] . ', <cite><a href="' . $this->plugins[$name]['AuthorURI'] . '">'
                        . sprintf(__('By %s'), $this->plugins[$name]['Author'])
                        . '</a></cite></dd>'
                    );

            $this->out .= '</dl>';

        }

        return;

    }



    /**
     * Creates the widget.
     *
     * Output is echoed.
     *
     * @return void
     */
    function widget_widget($args)
    {

        extract($args);

        /**
         * This hack prevents the widget administration from showing a pointless
         * and annoying title after the widget name.
         */
        if ($before_title == '%BEG_OF_TITLE%')
            return;        

        $active_plugins = get_option('active_plugins');

        /**
         * Remove hidden plugins form $active_plugins
         */
        if ($active_plugins && is_array($active_plugins))
            foreach($active_plugins as $k => $name)
                if (isset($this->options['hidden_plugins'][$name]))
                    unset($active_plugins[$k]);

        $title = empty($this->options['widget_title']) ? __('Currently Active Plugins') : apply_filters('widget_title', $this->options['widget_title']);

        if (!function_exists('get_plugins'))
            include('wp-admin/includes/plugin.php');

        $total_plugins = count(get_plugins());

        echo $before_widget . $before_title
            . $title
            . $after_title;

        /**
         * Don't show list if there are no active plugins or all active plugins
         * are hidden.
         */
        if (!$active_plugins || !is_array($active_plugins))
        {
            echo __('No plugins to show') . $after_widget;
            return;
        }



        if ($this->options['use_xsl'])
        {

            $this->get_plugins_xml($active_plugins);

            $xsl = new DOMDocument;
            $xsl->loadXML($this->options['widget_xsl']);

            $proc = new XSLTProcessor;

            $proc->setParameter(
                '',
                array(
                    'version'          => __('Version'),
                    'show_stats'       => $this->options['show_stats'],
                    'total_plugins'    => $total_plugins,
                    'active_plugins'   => count($active_plugins),
                    'inactive_plugins' => $total_plugins - count($active_plugins)
                    )
                );

            $proc->importStylesheet($xsl);

            echo $proc->transformToXML($this->xml);

            echo $after_widget;

        }
        else
        {

            $this->get_plugins();



            if ($this->options['show_stats'] == 'lite')
            {

                echo '<div class="jp-lip-stats">'
                    . sprintf(
                        __('%d Active Plugins.', 'jplip'),
                        count($active_plugins)
                        )
                    . '</div>';

            }
            elseif ($this->options['show_stats'] == 'full')
            {

                echo '<div class="jp-lip-stats">'
                    . sprintf(
                        __(
                            '%d plugins installed; %d active, and %d inactive.',
                            'jplip'
                            ),
                        $total_plugins,
                        count($active_plugins),
                        $total_plugins - count($active_plugins)
                        )
                    . '</div>';

            }



            echo "\n<ul>";

            foreach($active_plugins as $name)
                echo (isset($this->options['hidden_plugins'][$name])
                    ? ''
                    : "\n<li><a href=\"" . $this->plugins[$name]['PluginURI']
                        . '" class="jp-lip-plugin-title">'
                        . $this->plugins[$name]['Title'] . '</a>,<small> '
                        . __('Version') . ' ' . $this->plugins[$name]['Version']
                        . ' <cite><a href="' . $this->plugins[$name]['AuthorURI']
                        . '" class="jp-lip-plugin-author">'
                        . sprintf(__('By %s'), $this->plugins[$name]['Author'])
                        . '</a></cite></small></li>'
                    );

            echo "\n</ul>";

        }

        return;

    }



    /**
     * Creates the widget control form, note only option is title.
     *
     * Echoes output
     *
     * @todo Consider adding xsl on/off, and widget_caption options.
     * @return void
     */
    function widget_control()
    {

        if (!empty($_POST)) {
            $this->options['widget_title'] = (isset($_POST['jp-lip-widget-title'])
                ? strip_tags(stripslashes($_POST['jp-lip-widget-title']))
                : ''
                );
            update_option('jp_lip', $this->options);
        }

        echo '<p><label for="jp-lip-widget-title">' . __('Title:') . '
            <input type="text" class="widefat" id="jp-lip-widget-title" name="jp-lip-widget-title" value="' . attribute_escape($this->options['widget_title']) . '" />
            </label></p>
            <p><a href="plugins.php?page=jp-listinstalledplugins.php">' . __('Modify widget output templating', 'jplip') . '</a>.</p>';

    }



    /**
     * Adds show/hide link to plugin actions on the plugins page for active
     * plugins only.
     *
     * @return string HTML output
     */
    function filter_plugin_action_links($action_links, $plugin_file, $plugin_data, $context)
    {

        // Only show hide/show links for active plugins
        if (!is_plugin_active($plugin_file))
            return $action_links;

        /**
         * Add Hide/Show link depending on current status.
         */
        $action_links[] = (isset($this->options['hidden_plugins'][$plugin_file])
            ? '<a href="plugins.php?jp_lip[action]=show&amp;jp_lip[plugin]=' . $plugin_file . '" title="' . __('Show this plugin on List Installed Plugins') . '">' . __('Show') . '</a>'
            : '<a href="plugins.php?jp_lip[action]=hide&amp;jp_lip[plugin]=' . $plugin_file . '" title="' . __('Hide this plugin on List Installed Plugins') . '">' . __('Hide') . '</a>'
            );

        return $action_links;

    }



    function filter_plugin_action($links)
    {

        array_unshift(
            $links,
            '<a href="plugins.php?page=jp-listinstalledplugins.php">' . __('Options') . '</a>'
            );

        return $links;

    }



    /**
     * After each plugin a message will be displayed if that plugin was enabled
     * or disabled.
     *
     * Echoes output
     *
     * @return void
     */
    function action_after_plugin_row($plugin_file, $plugin_data, $context)
    {

        if (isset($this->messages[$plugin_file]))
            echo '<tr><td colspan="5" class="updated fade plugin-update" style="text-align:center;padding:5px;">'
                . $this->messages[$plugin_file]
                . '</td></tr>';

        return;

    }



    /**
     * If template tag mode si anabled this scans all posts and pages for the
     * template tag and inserts the list if found.
     *
     * @param string $content Post content as supplied by WP.
     * @return string Filtered $content
     */
    function filter_the_content($content)
    {

        /**
         * I have seen a couple of benchmarks stating that preg_match is faster
         * for simple searches such as this, however I have not performed any
         * specific benchmarking myself.
         */
        if (!preg_match('|<!--#list-installed-plugins-->|i', $content))
            return $content;

        /**
         * The idea of this is to not run on every time content is displayed
         * if possible
         */
        if ($this->options['post_id']) {
            if (!is_single() && !is_page())
            global $post;
        }

        $this->get_the_list();

        return preg_replace('|(<!--#list-installed-plugins-->)|is', $this->out, $content);

    }



    /**
     * If replace mode is enabled will replace a page's content with the list
     * if viewing a single page which is the specified page. Otherwise returns
     * the content untouched.
     *
     * @return string Filtered $content
     */
    function filter_the_content_replace($content)
    {

        global $wp_query;

        if (!$wp_query->is_page || $wp_query->post->ID != $this->options['post_id'])
            return $content;   

        $this->get_the_list();

        return $this->out;

    }

}

/**
 * Always initialize the class. The class is initialized into a variable incase
 * necessary for some reason, however this probabally is not required. Possible
 * PHP 4 requires this actually? I don't know, I stay well away from PHP4.
 */
$jp_lip = new jp_lip;
