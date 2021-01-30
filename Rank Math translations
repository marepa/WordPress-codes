<?php
    // custom function if Polylang & RankMath coexist
    // you have to add "{string}" to fields in RankMath .. e.g. "Archives %s" for "{archives_title} %s"
    // optionally you can replace __() with pll_register_string() AND pll__()
    function breadcrumbs() {
		if ( function_exists( 'rank_math_get_breadcrumbs' ) ) {
			$replace_strings = str_replace(
				array( '{home_title}', '{archives_title}', '{results_title}', '{404_title}' ),
				array( __( 'Home', 'textdomain' ), __( 'Achives', 'textdomain' ), __( 'Results', 'proxim' ), __( '404: content not found', 'textdomain' ), ),
				rank_math_get_breadcrumbs()
			);

			echo $replace_strings;
		}
	}
