<?php
/*
	Plugin Name: Zpp Widget
	Plugin URI: http://pp.zlotemysli.pl/widget
	Description: Widget losujący okładki dla Złotego Programu Partnerskiego - http://pp.zlotemysli.pl/
	Author: Marcin Kądziołka
	Version: 0.63
	Author URI: http://marcin.kadziolka.net/
	License: GPL2

	Copyright 2011 Marcin Kądziołka (email : widget@zlotemysli.pl)
        Based on a Helion Widget by Paweł Pela - http://paulpela.com/

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
	
	--------------------------------------------------------------------------
*/

define("ZPP_DEBUG", false);

$zpp_widget_version = '0.63';

/* Add our function to the widgets_init hook. */
add_action( 'widgets_init', 'zpp_load_widget' );
add_action( 'wp_print_styles', 'zpp_styles' );

function zpp_styles() {
	wp_register_style( 'zpp-styles', plugins_url("zpp-widget/css/zpp-plugin.css", dirname(__FILE__)) );
        wp_enqueue_style( 'zpp-styles' );
}

/* Function that registers our widget. */
function zpp_load_widget() {
	register_widget( 'Zpp_Widget' );
}

function zpp_is_addon_installed( $addon ) {
	if(in_array($addon, get_loaded_extensions())) {
		return true;
	} else {
		return false;
	}
}

function zpp_partnerlink_valid( $partnerlink ) {
	return ctype_alnum( $partnerlink );
}

class Zpp_Widget extends WP_Widget {
	
	function Zpp_Widget() {
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'zpp_widget', 'description' => 'Widget wyświetlający wybrane książki wydawnictwa Złote Myśli, zintegrowany z programem partnerskim', 'coversize' => '152x200' );

		/* Widget control settings. */
		$control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'zpp-widget' );

		/* Create the widget. */
		$this->WP_Widget( 'zpp-widget', 'Zpp Widget', $widget_ops, $control_ops );
	}
	
	function widget( $args, $instance ) {
		extract( $args );
		
		$title = apply_filters( 'widget_title', $instance['title'] );
		$coversize = $instance['coversize'];
		$visible_title = $instance['visible_title'];
		$visible_author = $instance['visible_author'];
		$visible_buy_button = $instance['visible_buy_button'];
		$visible_price = $instance['visible_price'];
		$only_free_products = $instance['only_free_products'];
		$campaign = 'zppwidget';
		$partnerlink = get_option( 'zpp_partnerlink' );
		$id_categories = get_option( 'zpp_categories' );
		$id_products = get_option( 'zpp_products' );

		// get all concatenated IDs
		$id_products_from_categories = Array();

		foreach( explode( ',', $id_categories ) as $id ) {
			if( $id != 0 ) {
				$ret_arr = Zpp_Widget::get_ids_for_category($id, $only_free_products);

				if( is_array($ret_arr) ) {
					$id_products_from_categories = array_merge( $id_products_from_categories, $ret_arr );
				}
			}
		}

		if( strlen( $id_products ) > 0 ) {
			$id_products_arr = explode( ',', $id_products );
		} else {
			$id_products_arr = Array();
		}

		$ids_to_randomize = array_merge( $id_products_arr, array_unique( $id_products_from_categories ) );

		if( sizeof( $ids_to_randomize ) == 0 )
			return;

		$rand_key = array_rand( $ids_to_randomize );
		$id_product = $ids_to_randomize[$rand_key];
		$product = $this->get_product( $id_product );
		$img_url = preg_replace( '/\d\d\dx\d\d\d/', $coversize, $product->image );
		$cached_img_url = $this->get_cover_from_cache( $img_url );

		if( $cached_img_url == null ) {
			$this->save_cover_to_cache( $img_url );
		} else {
			$img_url = $cached_img_url;
		}

		$product_url = str_replace( '/prod/', "/$partnerlink,$campaign/prod/", $product->url );
		$add_basket_url = 'http://www.zlotemysli.pl/' . $partnerlink . ',' . $campaign . '/koszyk/dodaj-produkt/' . $id_product . '.html';
		$product_name = htmlspecialchars( preg_replace( '/(.*)( - wersja.*)/i', '$1', $product->name ) );
		$product_price = preg_replace( '/\./', ',', $product->price );
		$product_price .= ' zł';

		foreach( $product->attributes->attribute as $attr ) {
			if( !strcmp($attr->name, 'Autor' ) ) {
				$product_author = htmlspecialchars( $attr->value );
				break;
			}
		}

		echo '<li id="zpp-widget" class="zpp_widget">' . "\n";

		if( $title )
			echo $before_title . $title . $after_title . "\n";

		echo '<div class="zpp_cover">' . "\n";
		echo '<a href="' . $product_url . '" target="_blank" alt="strona książki ' . $product_name . '"><img src="' . $img_url . '" alt="książka ' . $product_name . '" title="okładka książki ' . $product_name . '"></a>' . "\n";
		echo '</div>' . "\n";

		if( $visible_title )
			echo '<div class="zpp_product_title"><a href="' . $product_url . '" alt="ebook ' . $product_name . '" target="_blank">' . $product_name . '</a></div>' . "\n";

		if( $visible_author )
			echo '<div class="zpp_product_author">' . $product_author . '</div>' . "\n";

		if( $visible_price ) {
			if( $product_price == 0 ) {
				echo '<div class="zpp_product_price">DARMOWY</div>' . "\n";
			}
			else {
				echo '<div class="zpp_product_price">' . $product_price . '</div>' . "\n";
			}
		}

		if( $visible_buy_button )
			echo '<div class="zpp_buy_now"><a href="' . $add_basket_url . '" alt="kup ebook ' . $product_name . '" target="_blank">KUP TERAZ</a></div>' . "\n";

		echo '</li>' . "\n";
	}

	function prepare_cover_filename( $url ) {
		// url format:
		// http://get.zlotemysli.pl/products/image/000/004/774/82x122.jpg?20111128
		$arr_params = explode( 'products/image/', $url );
		$arr_tmp = explode('?', $arr_params[1] );
		$filename = str_replace( '/', '_', $arr_tmp[0] );

		return $filename;
	}

	function get_cover_from_cache( $url ) {
		$filename = $this->prepare_cover_filename( $url );
		$ret_url = '/wp-content/zpp-cache/covers/' . $filename;
		$src = ABSPATH . $ret_url;
		$mtime = @filemtime( $src );

		if( ( $mtime + 3600 *24 ) < time() )
			return null;

		return $ret_url;
	}

	function save_cover_to_cache( $url ) {
		$max_cache_filesize = 102400;
		$filename = $this->prepare_cover_filename( $url );
		$dest = ABSPATH . '/wp-content/zpp-cache/covers/' . $filename;
	
		$fp_src = @fopen( $url, 'r' );

		if( $fp_src == FALSE ) {

			if( ZPP_DEBUG ) {
				echo 'DEBUG: nie udało się otworzyć pliku ' . $url;
			}

			return false;
		}

		$data = "";

		while( !feof( $fp_src ) ) {
			$data .= fread( $fp_src, 8192);

			if( strlen( $data ) > $max_cache_filesize ) {
				fclose( $fp_src );

				if( ZPP_DEBUG ) {
					echo 'DEBUG: plik dłuższy niż ' . $max_cache_filesize;
				}

				return false;
			}
		}

		fclose( $fp_src );

		if(is_writable(ABSPATH . "/wp-content/zpp-cache/covers")) {
			$fp = @fopen( $dest, 'w' );

			if( ( !fwrite( $fp, $data) ) && ZPP_DEBUG ) {
				echo 'DEBUG: błąd zapisu okładki ' . $dest;
			}

			fclose( $fp );
			return true;
		} else if(mkdir(ABSPATH . "/wp-content/zpp-cache/covers", 0775, true)) {
			$fp = @fopen( $dest, 'w' );

			if( ( !fwrite( $fp, $data) ) && ZPP_DEBUG ) {
				echo 'DEBUG: po stworzeniu katalogu - błąd zapisu okładki ' . $dest;
			}

			fclose( $fp );
			return true;
		}

		return false;
	}

	public static function get_xml_from_cache( $url ) {
		$arr_params = explode( '?', $url );
		$arr_filename = explode( '/', $arr_params[0] );
		$filename = array_pop( $arr_filename );
		$params = strtr( $arr_params[1], '=,', '__' );
		$src = ABSPATH . '/wp-content/zpp-cache/xml/' . $filename . '_' . $params;
		$mtime = @filemtime( $src );

		if( ( $mtime + 3600 * 24 * 3 ) < time() )
			return null;

		$fp = @fopen( $src, 'r' );

		if( $fp ) {
			$ret = @fread( $fp, filesize($src) );
			fclose( $fp );
			return $ret;
		}

		return null;
	}

	public static function save_xml_to_cache( $url, $xml ) {
		// url format:
		// http://export.zlotemysli.pl/feeds/products.xml?category_id=
		// get filename.xml and parameters
		$arr_params = explode( '?', $url );
		$arr_filename = explode( '/', $arr_params[0] );
		$filename = array_pop( $arr_filename );
		$params = strtr($arr_params[1], '=,', '__');
		$dest = ABSPATH . '/wp-content/zpp-cache/xml/' . $filename . '_' . $params;
	
		if(is_writable(ABSPATH . "/wp-content/zpp-cache/xml")) {
			$fp = @fopen( $dest, 'w' );

			if( ( !fwrite( $fp, $xml) ) && ZPP_DEBUG ) {
				echo 'DEBUG: błąd zapisu xmla ' . $dest;
			}

			fclose( $fp );
			return true;
		} else if(mkdir(ABSPATH . "/wp-content/zpp-cache/xml", 0775, true)) {
			$fp = @fopen( $dest, 'w' );

			if( ( !fwrite( $fp, $xml) ) && ZPP_DEBUG ) {
				echo 'DEBUG: po stworzeniu katalogu - błąd zapisu xmla ' . $dest;
			}

			fclose( $fp );
			return true;
		}

		return false;
	}

	public static function get_xml( $url ) {
		if( !zpp_is_addon_installed( "SimpleXML") ) {

			if( ZPP_DEBUG ) 
				echo "DEBUG: brak modułu SimpleXML";

			return null;
		}

		if( ZPP_DEBUG ) 
			libxml_use_internal_errors(false);
		else
			libxml_use_internal_errors(true);

		$xml_string = Zpp_Widget::get_xml_from_cache( $url );

		if( $xml_string ) {
			$loaded_xml = simplexml_load_string($xml_string, "SimpleXMLElement", LIBXML_NOCDATA);
			return $loaded_xml;
		}
				
		if( ini_get( "allow_url_fopen" ) && zpp_is_addon_installed( "curl" ) ) {
			global $zpp_widget_version;
			$cu = curl_init();
			curl_setopt($cu, CURLOPT_URL, $url); 
			curl_setopt($cu, CURLOPT_RETURNTRANSFER, true); 
			curl_setopt($cu, CURLOPT_POST, false); 
			curl_setopt($cu, CURLOPT_USERAGENT, 'ZppWidget/' . $zpp_widget_version); 
			$xml_string = curl_exec($cu);

			if( strlen($xml_string) == 0 ) {

				if( ZPP_DEBUG ) 
					echo 'DEBUG: zerowa długość XMLa ' . $url;

				return null;
			}

			$xml_string = str_replace('xmlns:pasaz=', 'ns:pasaz=', $xml_string);
			Zpp_Widget::save_xml_to_cache( $url, $xml_string );
			$loaded_xml = simplexml_load_string($xml_string, "SimpleXMLElement", LIBXML_NOCDATA);
			$noerrors_xml = true;
			curl_close($cu);
		} else {
			$noerrors_xml = false;
		}

		if( $noerrors_xml && $loaded_xml ) {
			return $loaded_xml;
		}

		return null;
	}


	public static function get_ids_for_category( $id_category, $only_free=false ) {
		$url = "http://export.zlotemysli.pl/feeds/zppwidget.xml?category_id=" . $id_category;
		$loaded_xml = Zpp_Widget::get_xml( $url );

		if( $loaded_xml != null ) {
			$ret_arr = Array();
			$offers = $loaded_xml->Body->loadOffers->offers;

			foreach( $offers->offer as $offer ) {
				foreach( $offer->attributes->attribute as $attr ) {
					if( !strcmp( $attr->name, 'zm:productTypeId' ) ) {
						if( $attr->value < 32 ) {
							if( !$only_free ) { 
								$ret_arr[] = (int) $offer->id;
							}
							elseif ($offer->price == 0) {
								$ret_arr[] = (int) $offer->id;
							}
						}

						break;
					}
				}


			}
		} else {
			return null;
		}

		return $ret_arr;
	}
	
	function get_product( $id_product ) {
		$url = 'http://export.zlotemysli.pl/feeds/zppwidget.xml';
		$loaded_xml = $this->get_xml( $url );

		if( $loaded_xml != null ) {
			$offers = $loaded_xml->Body->loadOffers->offers;

			foreach( $offers->offer as $offer ) {
				if( $offer->id == $id_product ) {
					return $offer;
				}
			}
		}

		return null;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags (if needed) and update the widget settings. */
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['coversize'] = strip_tags( $new_instance['coversize'] );
		$instance['visible_title'] = strip_tags( $new_instance['visible_title'] );
		$instance['visible_author'] = strip_tags( $new_instance['visible_author'] );
		$instance['visible_buy_button'] = strip_tags( $new_instance['visible_buy_button'] );
		$instance['visible_price'] = strip_tags( $new_instance['visible_price'] );
		$instance['only_free_products'] = strip_tags( $new_instance['only_free_products'] );

		return $instance;
	}
	
	function form( $instance ) {

		/* Set up some default widget settings. */
		$defaults = array( 'title' => 'Polecana książka' );
		$instance = wp_parse_args( (array) $instance, $defaults );
		$selected = ' selected="selected" ';
		$checked = ' checked="checked" ';
		?>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">Tytuł:</label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" class="widefat" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'coversize' ); ?>">Rozmiar okładki:</label>
			<select id="<?php echo $this->get_field_id( 'coversize' ); ?>" name="<?php echo $this->get_field_name( 'coversize' ); ?>" class="widefat" style="width:100%;">
				<option <?php if($instance['coversize'] == "82x122") echo $selected; ?>>82x122</option>
				<option <?php if($instance['coversize'] == "152x200") echo $selected; ?>>152x200</option>
			</select>
		</p>
		<p>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'visible_title' ); ?>" name="<?php echo $this->get_field_name( 'visible_title' ); ?>" <?php if($instance['visible_title']) echo $checked; ?> />
			<label for="<?php echo $this->get_field_id( 'visible_title' ); ?>">Wyświetlać tytuł książki?</label>
			<br/>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'visible_author' ); ?>" name="<?php echo $this->get_field_name( 'visible_author' ); ?>" <?php if($instance['visible_author']) echo $checked; ?> />
			<label for="<?php echo $this->get_field_id( 'visible_author' ); ?>">Wyświetlać autora książki?</label>
			<br/>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'visible_price' ); ?>" name="<?php echo $this->get_field_name( 'visible_price' ); ?>" <?php if($instance['visible_price']) echo $checked; ?> />
			<label for="<?php echo $this->get_field_id( 'visible_price' ); ?>">Wyświetlać cenę?</label>
			<br/>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'visible_buy_button' ); ?>" name="<?php echo $this->get_field_name( 'visible_buy_button' ); ?>" <?php if($instance['visible_buy_button']) echo $checked; ?> />
			<label for="<?php echo $this->get_field_id( 'visible_buy_button' ); ?>">Wyświetlać przycisk "Dodaj do koszyka"?</label>
			<br/>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'only_free_products' ); ?>" name="<?php echo $this->get_field_name( 'only_free_products' ); ?>" <?php if($instance['only_free_products']) echo $checked; ?> />
			<label for="<?php echo $this->get_field_id( 'only_free_products' ); ?>">Wyświetlać tylko darmowe produkty</label>
			
		</p>
<?php
		$categories_opt_name = 'zpp_categories';
		$products_opt_name = 'zpp_products';
		$categories_opt_val = get_option( $categories_opt_name );
		$products_opt_val = get_option( $products_opt_name );

		if( ( strlen( $products_opt_val ) == 0 ) && ( strlen( $categories_opt_val ) == 0 ) ) {
?>
		<p>
			<strong>Uwaga! Nie wybrałeś żadnych kategorii ani produktów w menu "Ustawienia -> ZPP Widget". Okładka nie zostanie wyświetlona.</strong>
		</p>
<?php
		}
	}
}

/* ---------------------------------------------------------------------------
	Menu creation code
*/

add_action('admin_menu', 'zppwidget_plugin_menu');

function zppwidget_plugin_menu() {

  add_options_page('Opcje Widgetu ZPP', 'ZPP Widget', 'manage_options', 'zppwidget-options', 'zppwidget_plugin_options');

}

// zwraca pary ID - nazwa kategorii
function zpp_get_categories_from_server( ) {
	$loaded_xml = Zpp_Widget::get_xml( 'http://export.zlotemysli.pl/feeds/categories.xml' );

	if( $loaded_xml != null ) {
		$ret_arr = Array();
		$categories = $loaded_xml->categories;

		foreach( $categories->category as $category ) {
			$ret_arr[ (int) $category->id] = (string) $category->name;
		}
	} else {
		echo $before_title . "Zpp Widget Error" . $after_title;
		echo '<p><strong>Nie znaleziono opisu kategorii w wydawnictwie Złote Myśli. Możliwe jest również, że serwer Złotych Myśli chwilowo nie odpowiada.</strong></p>';
		echo '<p><strong>Jeśli problem będzie się powtarzał, zgłoś błąd do wydawnictwa.</strong></p>';
		$ret_arr = null;
	}

	return $ret_arr;
}

function zppwidget_plugin_options() {

  if ( ! current_user_can( 'manage_options' ) )  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }

	if( !ini_get( 'allow_url_fopen' ) ) {
		wp_die('Brak ustawionej opcji <strong>allow_url_open</strong> w konfiguracji Twojego serwera. Skontaktuj się z administratorem.');
	}

	if( !zpp_is_addon_installed( 'curl' ) ) {
		wp_die('Brak zainstalowanego modułu <strong>curl</strong> na Twoim serwerze. Skontaktuj się z administratorem.');
	}

	if( !zpp_is_addon_installed( 'SimpleXML' ) ) {
		wp_die('Brak zainstalowanego modułu <strong>SimpleXML</strong> na Twoim serwerze. Skontaktuj się z administratorem.');
	}


        $hidden_partnerlink_name = 'zpp_submit_partnerlink';
        $hidden_categories_name = 'zpp_submit_categories';
	$hidden_products_name = 'zpp_submit_products';
	$partnerlink_opt_name = 'zpp_partnerlink';
	$categories_opt_name = 'zpp_categories';
	$products_opt_name = 'zpp_products';
  
	$partnerlink_opt_val = get_option( $partnerlink_opt_name );
	$categories_opt_val = get_option( $categories_opt_name );
	$products_opt_val = get_option( $products_opt_name );
	$data_saved = false;

	if( isset( $_POST[$hidden_partnerlink_name] ) && $_POST[ $hidden_partnerlink_name ] == 'Y' ) {

		if( zpp_partnerlink_valid( $_POST['partnerlink'] ) ) {
			update_option( $partnerlink_opt_name, $_POST['partnerlink'] );
			$partnerlink_opt_val = $_POST['partnerlink'];
			$data_saved = true;
		} else {
?>
<div class="error"><p><strong>Uwaga! Podałeś link partnerski, który nie jest poprawny w Złotym Programie Partnerskim. Link partnerski składa się tylko z liter i cyfr, np. mojlink, polecam, ala123.</strong></p></div>
<?php
		}
	}


	if( isset( $_POST[$hidden_categories_name] ) && $_POST[ $hidden_categories_name ] == 'Y' ) {
        // Read their post value
		if( is_array( $_POST['category'] ) ) {
			foreach( $_POST['category'] as $categoryid ) {
				$cat_str .= $categoryid . ',';
			}
		}

		$cat_str = rtrim( $cat_str, ',' );

		update_option( $categories_opt_name, $cat_str );
		$categories_opt_val = $cat_str;
		$data_saved = true;
	}

	if( isset( $_POST[$hidden_products_name] ) && $_POST[ $hidden_products_name ] == 'Y' ) {
        // Read their post value
		update_option( $products_opt_name, $_POST['products'] );
		$products_opt_val = $_POST['products'];
		$data_saved = true;
	}

	$categories = zpp_get_categories_from_server();
	$categories_saved = explode( ",", $categories_opt_val );

	if( ( strlen( $products_opt_val ) == 0 ) && ( strlen( $categories_opt_val ) == 0 ) ) {
?>
<div class="error"><p><strong>Uwaga! Nie wybrałeś żadnych kategorii ani produktów. Okładka nie zostanie wyświetlona.</strong></p></div>
<?php
	}

	if( strlen( $partnerlink_opt_val ) == 0 ) {
?>
<div class="updated"><p><strong>Uwaga! Nie wpisałeś swojego linka partnerskiego, nie będą poprawnie naliczane prowizje po kliknięciu w okładkę produktu.</strong></p></div>
<?php
	}

	if( $data_saved ) {
		// Put an settings updated message on the screen

?>
<div class="updated"><p><strong>Zmiany zostały zapisane. Jeśli jeszcze tego nie zrobiłeś dodaj widget ZPP w menu "Wygląd -> Widgety".</strong></p></div>
<?php
	}

?>

<div class="wrap">
<h2>Zpp Widget</h2>
<p>Poniżej wpisz Twój link partnerski, który wybrałeś przy rejestracji w Złotym Programie Parterskim</p>
<form name="formpartnerlink" method="post" action="">
<input type="hidden" name="<?php echo $hidden_partnerlink_name; ?>" value="Y" />
<input type="text" name="partnerlink" value="<?php echo "$partnerlink_opt_val"?>" />
<p class="submit">
<input type="submit" name="Submit" class="button-primary" value="Zapisz" />
</p>
</form>

<?php
if( $categories != null ) { ?>
<p>Tutaj możesz wybrać kategorie, z których mają się wyświetlać okładki.</p>
<p>Możesz też nie wybierać żadnej kategorii, tylko poniżej wybrać konkretne publikacje, które chcesz promować.</p>
<form name="formcategories" method="post" action="">
<input type="hidden" name="<?php echo $hidden_categories_name; ?>" value="Y">
<?php foreach( $categories as $id=>$name ) {
$selected = '';

if( in_array( $id, $categories_saved ) ) {
	$selected = 'checked';
}
?>
<input type="checkbox" name="category[]" value="<?php echo "$id";?>" <?php echo "$selected";?> /><?php echo "$name"?><br/>
<?php } ?>
<p class="submit">
<input type="submit" name="Submit" class="button-primary" value="Zapisz" />
</p>
</form>
<?php
}
?>
<hr>
<p>Tutaj możesz wybrać konkretne publikacje, które chcesz promować.</p>
<p>Nie musisz wybierać żadnej z nich, jeśli powyżej zaznaczyłeś którąś z kategorii.</p>
<p><strong>TODO, na razie trzeba wpisać id rozdzielone przecinkiem</strong></p>
<form name="formproducts" method="post" action="">
<input type="hidden" name="<?php echo $hidden_products_name; ?>" value="Y">
<textarea rows="8" cols="60" name="products">
<?php echo "$products_opt_val"; ?>
</textarea>
<p class="submit">
<input type="submit" name="Submit" class="button-primary" value="Zapisz" />
</p>
</form>
</div>

<?php
}
?>
