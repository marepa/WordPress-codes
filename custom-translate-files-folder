<?php
  // Code to be placed in functions.php of your theme or a custom plugin file.
    add_filter( 'load_textdomain_mofile', 'load_custom_plugin_translation_file', 10, 2 );

    /*
    * Replace 'textdomain' with your plugin's textdomain. e.g. 'woocommerce'. 
    * File to be named, for example, yourtranslationfile-en_GB.mo
    * File to be placed, for example, wp-content/lanaguages/textdomain/yourtranslationfile-en_GB.mo
    */
    function load_custom_plugin_translation_file( $mofile, $domain ) {
        if( ! is_admin() ) {
            $custom_translation_domain = array( 'woocommerce-subscriptions', 'woocommerce' );
            if ( in_array( $domain, $custom_translation_domain ) ) {
                $mofile = WP_LANG_DIR . '/loco/plugins/'. $domain .'-' . get_locale() . '.mo';
            }
        }

        return $mofile;
    }
