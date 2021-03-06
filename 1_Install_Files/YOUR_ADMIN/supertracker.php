<?php
/**
 * @package supertracker
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @developer Created by Mark Stephens, http://www.phpworks.co.uk
 * @developer Added keywords filters by Monika Math�, http://www.monikamathe.com
 * @developer Added keywords processing by Andrew Berezin, http://eCommerce-Service.com
 * @developer Ported to Zen-Cart by Andrew Berezin, http://eCommerce-Service.com
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version Based $Id: supertracker.php v 3.20b 21 Mar 2006 Mark Stephens $
 * @version $Id: supertracker.php, v 1.0.0 09.05.2007 17:40 Andrew Berezin andrew@ecommerce-service.com $
 */

// ********** PAY PER CLICK CONFIGURATION SECTION ************
// Pay per click referral URLs used - to make this work you have to set up your pay-per-click
// URLs like this : http://www.yoursite.com/catalog/index.php?ref=xxx&keyw=yyy
// where xxx is a code representing the PPC service and yyy is the keyword being used
// to generate that referral. Here's an example :
// http://www.yoursite.com/catalog/index.php?ref=googled&keyw=gameboy
// which might be used for the keyword "gameboy" in a google adwords campaign.
// The keyword part is optional - if you don't use it in a particular campaign, you
// Just set up the $ppc array like that in the example for googlead below
$ppc = array ('googlead' => array ('title' => 'Google Adwords', 'keywords' => 'shortcode1:friendlyterm1,shortcode1:friendlyterm2'));
//Set the following to true to enable the PPC referrer report
//Eventually, this will probably be moved into the configuration menu
//in admin, where it really should be!
define ('SUPERTRACKER_USE_PPC', false);
// ********** PAY PER CLICK CONFIGURATION SECTION EOF ************

  require('includes/application_top.php');

  @ini_set('display_errors', '1');
//  error_reporting(E_ALL);

  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

  @set_time_limit(0);

  function build_geo_graph($geo_hits, $country_names, $total_hits) {
    $max_pixels = 400;
    arsort($geo_hits);
    $i = 0;
    $geo_graph = array();
    foreach($geo_hits as $country_code => $num_hits) {
      $bar_length = ($num_hits/$total_hits) * $max_pixels;
      $percent_hits = round(($num_hits/$total_hits) * 100, 2);
      //Create a random colour for each bar
      srand((double)microtime()*1000000);
      $r = dechex(rand(0, 255));
      $g = dechex(rand(0, 255));
      $b = dechex(rand(0, 255));
      $geo_graph[$i]['country'] = ($country_names[$country_code] != '' ? $country_names[$country_code] : $country_code);
      $geo_graph[$i]['hits_graph'] = '<div style="display:justify; border:1px solid #000; height:10px; background:#' . $r . $g . $b . '; width:' . $bar_length . '"></div>';
      $geo_graph[$i]['hits'] = $percent_hits . '%';
      $i++;
    }
    return $geo_graph;
  }//end function

function supertracker_get_arg($query, $argname, $convert=false) {
  $argvalue = false;
  $vars = explode('&', $query);
  foreach($vars as $k=>$var) {
    if($var == "") continue;
    if(preg_match('`^' . $argname . '=(.*)$`i', $var, $argv) == 0) continue;
    if(!isset($argv[1])) return(false);
//    $argvalue = rawurldecode($argv[1]);
    $argvalue = $argv[1];
    if($convert) {
      $eval_str = "\$argvalue = " . sprintf($convert, $argvalue) . ';';
      eval($eval_str);
    }
    break;
  }
  return($argvalue);
}

function supertracker_get_keywords($days=false) {
  global $db;
  require('includes/searchengines.php');
  $keywords_used = array();
  $keywords_row = $db->Execute("SELECT referrer, referrer_query_string
                                FROM " . TABLE_SUPERTRACKER . "
                                WHERE referrer_query_string > '' " .
                                ($days ? "AND DATE_ADD(time_arrived, INTERVAL " . $days . " DAY) >= NOW() " : ""));
//  var_dump($keywords_row->RecordCount());echo '<br />';
  $cc = array();
  while (!$keywords_row->EOF) {
    $cc['total']++;
    $raw_search = false;
    $url = parse_url(urldecode($keywords_row->fields["referrer"] . '?' . $keywords_row->fields["referrer_query_string"]));
    if(empty($url["query"])) continue;
    foreach($search as $key => $val) {
      foreach($val['rule'] as $pattern) {
      if (preg_match('`' . $pattern . '`i', $url["host"] . $url["path"], $regs) == 0) {continue;}
        $raw_search = supertracker_get_arg($url["query"], $val["argv"], @$val["conv"]);
/*
        if(isset($val["encode"]) && $val["encode"] != 'utf-8') {
          $raw_search = iconv($val["encode"], 'utf-8', $raw_search);
        }
        $raw_search = iconv('utf-8', 'windows-1251', $raw_search);
*/
          $cc['raw_search']++;
//  if(is_ruUTF8($raw_search)){var_dump($cc['total'], $raw_search, is_ruUTF8($raw_search), UTF8toCP1251($raw_search));echo '<br />';}
          if(CHARSET == 'windows-1251' && is_ruUTF8($raw_search)) $raw_search = UTF8toCP1251($raw_search);
        break 2;
      }
    }
    if($raw_search === false) {
      $cc['raw_no_search']++;
      $key_array = explode ('&', $keywords_row->fields['referrer_query_string']);
      for ($i=0; $i<sizeof($key_array); $i++) {
        $keywords = false;
        if (substr($key_array[$i], 0,2) == 'q=') {
          $keywords = str_replace('+', ' ', substr($key_array[$i],2, strlen($key_array[$i])-2));
        }
        if (substr($key_array[$i], 0,2) == 'p=') {
          $keywords = str_replace('+', ' ', substr($key_array[$i],2, strlen($key_array[$i])-2));
        }
        if (strstr($key_array[$i], 'query=')) {
          $keywords = str_replace('+', ' ', substr($key_array[$i],6, strlen($key_array[$i])-6));
        }
        if (strstr($key_array[$i], 'keyword=')) {
          $keywords = str_replace('+', ' ', substr($key_array[$i],8, strlen($key_array[$i])-8));
        }
        if (strstr($key_array[$i], 'keywords=')) {
          $keywords = str_replace('+', ' ', substr($key_array[$i],9, strlen($key_array[$i])-9));
        }
      }
    }
    $keywords = trim($raw_search);
    if(!empty($keywords)) $keywords_used[$keywords]++;
    $keywords_row->MoveNext();
  }
//var_dump($cc);echo '<br />';
  arsort($keywords_used);
  $keywords_data = array();
  $i = 0;
  foreach($keywords_used as $kw => $hits) {
    $keywords_data[$i]['keywords'] = $kw;
    $keywords_data[$i]['hits'] = $hits;
    $i++;
  }
  return $keywords_data;
}

////////////////////////////////////////////////

  if (isset($_GET['action'])) $action = $_GET['action'];
  else $action = false;
  if ($action == 'del_rows') {
    $db->Execute("DELETE FROM " . TABLE_SUPERTRACKER . " ORDER by tracking_id ASC LIMIT " . (int)$_POST['num_rows']);
  }
?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<script language="javascript" src="includes/menu.js"></script>
<script language="javascript" src="includes/general.js"></script>
<script type="text/javascript">
  <!--
  function init()
  {
    cssjsmenu('navbar');
    if (document.getElementById)
    {
      var kill = document.getElementById('hoverJS');
      kill.disabled = true;
    }
  }
  // -->
</script>
<style>
/* data table */
.dataTableHeadingRow { background-color: #C9C9C9; }
.dataTableHeadingContent { font-family: Verdana, Arial, sans-serif; font-size: 10px; color: #000000; font-weight: bold; }
.dataTableRow { background-color: #F0F1F1; }
.dataTableRowSelected { background-color: #DEE4E8; }
.dataTableRowOver { background-color: #FFFFFF; cursor: pointer; cursor: hand; }
.dataTableContent { font-family: Verdana, Arial, sans-serif; font-size: 10px; color: #000000; }
</style>
</head>
<body onload="init()">
<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top">
    <table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td>
            <div class="supertracker_contact">
            <strong><?php echo TEXT_DATABASE_INFO; ?></strong>
<?php
            $maint_result = $db->Execute("SELECT tracking_id, time_arrived FROM " . TABLE_SUPERTRACKER . " ORDER BY tracking_id ASC");
            echo '<span class="supertracker_text">' . sprintf(TEXT_TABLE_DATABASE, $maint_result->RecordCount(), zen_date_short($maint_result->fields['time_arrived'])) . '</span><br />';
            echo zen_draw_form('del_rows', FILENAME_SUPERTRACKER, 'action=del_rows', 'post') . zen_hide_session_id() . TEXT_TABLE_DELETE . ' ' . '<input name="num_rows" size=10><input type="submit" value="' . TEXT_BUTTON_GO  . '"></form>';
?>
            </div>

            <div class="supertracker_text">
<?php
$report_array = array(
                  array('id' => "", 'text' => TABLE_TEXT_MENU_TEXTE),
                  array('id' => "refer", 'text' => TEXT_TOP_REFERRERS),
                  array('id' => "success_refer", 'text' => TEXT_TOP_SALES),
                  array('id' => "geo", 'text' => TEXT_VISITORS),
                  array('id' => "keywords", 'text' => TEXT_SEARCH_KEYWORDS),
                  array('id' => "keywords_last24", 'text' => TEXT_SEARCH_KEYWORDS_24),
                  array('id' => "keywords_last72", 'text' => TEXT_SEARCH_KEYWORDS_3),
                  array('id' => "keywords_lastweek", 'text' => TEXT_SEARCH_KEYWORDS_7),
                  array('id' => "keywords_lastmonth", 'text' => TEXT_SEARCH_KEYWORDS_30),
                  array('id' => "landing", 'text' => TEXT_TOP_LANDING_PAGES),
                  array('id' => "landing_added", 'text' => TEXT_TOP_LANDING_PAGES_NO_SALE),
                  array('id' => "exit", 'text' => TEXT_TOP_EXIT_PAGES),
                  array('id' => "exit_added", 'text' => TEXT_TOP_EXIT_PAGES_NO_SALE),
                  array('id' => "ave_clicks", 'text' => TEXT_AVERAGE_CLICKS),
                  array('id' => "ave_time", 'text' => TEXT_AVERAGE_TIME_SPENT),
                  array('id' => "prod_coverage", 'text' => TEXT_PRODUCTS_VIEWED_REPORT),
                  array('id' => "last_ten", 'text' => TEXT_LAST_TEN_VISITORS),
                );
if (SUPERTRACKER_USE_PPC) {
$report_array[] = array('id' => "ppc_summary", 'text' => TEXT_PPC_REFERRAL);
}

$_GET['report'] = zen_db_input($_GET['report']);

              echo zen_draw_form('report_selector', FILENAME_SUPERTRACKER, '', 'get') . zen_hide_session_id() . TABLE_TEXT_MENU_DESC_TEXTE . ' ' . zen_draw_pull_down_menu('report', $report_array, zen_db_input($_GET['report']), 'onChange="this.form.submit();"');
?>
              <noscript><input type="submit" value="GO"></noscript>
              </form>
              </div>
          </div>
        </td>
      </tr>

<?php
if (zen_not_null($_GET['report'])) {
  $headings=array();
  $row_data=array();
  switch ($_GET['report']) {
    case 'refer':
      $title = TEXT_TOP_REFFERING_URL;
      $headings[] = TEXT_RANKING;
      $headings[] = TEXT_REFFERING_URL;
      $headings[] = TEXT_NUMBER_OF_HITS;
      $row_data[] = 'referrer';
      $row_data[] = 'total';
      $tracker_query_raw = "SELECT referrer, COUNT(*) as total FROM " . TABLE_SUPERTRACKER . " WHERE referrer>'' GROUP BY referrer ORDER BY total DESC";
      break;

    case 'success_refer':
      $title = TEXT_SUCCESSFUL;
      $headings[] = TEXT_SERIAL;
      $headings[] = TEXT_REFFERING_URL;
      $headings[] = TEXT_NUMBER_OF_SALES;
      $row_data[] = 'referrer';
      $row_data[] = 'total';
      $tracker_query_raw = "SELECT referrer, COUNT(*) as total FROM " . TABLE_SUPERTRACKER . " WHERE completed_purchase = 'true' AND referrer > '' GROUP BY referrer ORDER BY total DESC";
      break;

    case 'landing':
      $title = TEXT_LANDING_PAGE;
      $headings[] = TEXT_SERIAL;
      $headings[] = TEXT_LANDING_PAGE;
      $headings[] = TEXT_NUMBER_OF_OCCURRENCES;
      $row_data[] = 'landing_page';
      $row_data[] = 'total';
      $tracker_query_raw="SELECT landing_page, landing_page_name, COUNT(*) as total FROM " . TABLE_SUPERTRACKER . " GROUP BY landing_page ORDER BY total DESC";
      break;

    case 'exit':
      $title = TEXT_EXIT_PAGE;
      $headings[] = TEXT_SERIAL;
      $headings[] = TEXT_EXIT_PAGE;
      $headings[] = TEXT_NUMBER_OF_OCCURRENCES;
      $row_data[] = 'exit_page';
      $row_data[] = 'total';
      $tracker_query_raw="SELECT exit_page, exit_page_name, COUNT(*) as total FROM " . TABLE_SUPERTRACKER . " WHERE completed_purchase='false' GROUP BY exit_page ORDER BY total DESC";
      break;

    case 'exit_added':
      $title = TEXT_TOP_EXIT_NO_SALE;
      $headings[] = TEXT_SERIAL;
      $headings[] = TEXT_EXIT_PAGE;
      $headings[] = TEXT_NUMBER_OF_OCCURRENCES;
      $row_data[] = 'exit_page';
      $row_data[] = 'total';
      $tracker_query_raw="SELECT exit_page, COUNT(*) as total FROM " . TABLE_SUPERTRACKER . " WHERE completed_purchase='false' AND added_cart='true' GROUP BY exit_page ORDER BY total DESC";
      break;

    case 'ave_clicks':
      $title = TEXT_CLICKS_BY_REFFERRER_REPORT;
      $headings[] = TEXT_SERIAL;
      $headings[] = TEXT_REFFERING_URL;
      $headings[] = TEXT_NUMBER_OF_CLICKS;
      $row_data[] = 'referrer';
      $row_data[] = 'ave_clicks';
      $tracker_query_raw="SELECT referrer, AVG(num_clicks) as ave_clicks FROM " . TABLE_SUPERTRACKER . " WHERE referrer>'' GROUP BY referrer ORDER BY ave_clicks DESC";
      break;

    case 'ave_time':
      $title = TEXT_AVERAGE_TIME_ON_SITE_BY;
      $headings[] = TEXT_SERIAL;
      $headings[] = TEXT_REFFERING_URL;
      $headings[] = TEXT_AVERAGE_LENGTH_OF_TIME;
      $row_data[] = 'referrer';
      $row_data[] = 'ave_time';
      $tracker_query_raw = "SELECT referrer, FORMAT(AVG(UNIX_TIMESTAMP(last_click) - UNIX_TIMESTAMP(time_arrived))/60,2) AS ave_time FROM " . TABLE_SUPERTRACKER . " WHERE referrer>'' GROUP BY referrer ORDER BY ave_time DESC";
      break;

    case 'keywords_last24':
      $title = TABLE_TEXT_KEY_PHRASE_24;
      $headings[] = TEXT_SERIAL;
      $headings[] = TABLE_TEXT_KEY_PHRASE_24;
      $headings[] = TEXT_NUMBER_OF_HITS;
      $row_data[] = 'keywords';
      $row_data[] = 'hits';
      $tracker_data = supertracker_get_keywords(1);
      break;

    case 'keywords_last72':
      $title = TABLE_TEXT_KEY_PHRASE_3;
      $headings[] = TEXT_SERIAL;
      $headings[] = TABLE_TEXT_KEY_PHRASE_3;
      $headings[] = TEXT_NUMBER_OF_HITS;
      $row_data[] = 'keywords';
      $row_data[] = 'hits';
      $tracker_data = supertracker_get_keywords(3);
      break;

    case 'keywords_lastweek':
      $title = TABLE_TEXT_KEY_PHRASE_7;
      $headings[] = TEXT_SERIAL;
      $headings[] = TABLE_TEXT_KEY_PHRASE_7;
      $headings[] = TEXT_NUMBER_OF_HITS;
      $row_data[] = 'keywords';
      $row_data[] = 'hits';
      $tracker_data = supertracker_get_keywords(7);
      break;

    case 'keywords_lastmonth':
      $title = TABLE_TEXT_KEY_PHRASE_30;
      $headings[] = TEXT_SERIAL;
      $headings[] = TABLE_TEXT_KEY_PHRASE_30;
      $headings[] = TEXT_NUMBER_OF_HITS;
      $row_data[] = 'keywords';
      $row_data[] = 'hits';
      $tracker_data = supertracker_get_keywords(30);
      break;

    case 'keywords':
      $title = TABLE_TEXT_KEY_PHRASE;
      $headings[] = TEXT_SERIAL;
      $headings[] = TABLE_TEXT_KEY_PHRASE;
      $headings[] = TEXT_NUMBER_OF_HITS;
      $row_data[] = 'keywords';
      $row_data[] = 'hits';
      $tracker_data = supertracker_get_keywords();
      break;

    case 'geo':
      $title = TABLE_TEXT_COUNTRY;
      $headings[] = TEXT_SERIAL;
      $headings[] = TABLE_TEXT_COUNTRY;
      $headings[] = '';
      $headings[] = '';
      $row_data[] = 'country';
      $row_data[] = 'hits_graph';
      $row_data[] = 'hits';
      $geo_row = $db->Execute("SELECT COUNT(*) AS count, s.country_code, c.countries_name
                               FROM " . TABLE_SUPERTRACKER . " s
                                 LEFT JOIN " . TABLE_COUNTRIES . " c ON (c.countries_iso_code_2 = s.country_code)
                               GROUP BY country_code");
      $geo_hits = array();
      $country_names = array();
      $total_hits = 0;
      while (!$geo_row->EOF) {
        $total_hits += $geo_row->fields['count'];
        $country_code = strtolower($geo_row->fields['country_code']);
        $geo_hits[$country_code] = $geo_row->fields['count'];
        $country_names[$country_code] = $geo_row->fields['countries_name'];
        $geo_row->MoveNext();
      }
      $tracker_data = build_geo_graph($geo_hits, $country_names, $total_hits);
      break;

    case 'prod_coverage':
      $title = TABLE_TEXT_PRODUCT_COVERAGE_REPORT;
      $headings[] = TEXT_SERIAL;
      $headings[] = TABLE_TEXT_PRODUCT_NAME;
      $headings[] = TABLE_TEXT_NUMBER_OF_VIEWING;
      $row_data[] = 'prod_name';
      $row_data[] = 'hits';
      if (isset($_GET['agent_match'])) {
        $agent_match = zen_db_input($_GET['agent_match']);
        $match_agent_string = " AND browser_string like '%" . $agent_match . "%'";
      } else {
        $match_agent_string = '';
        $agent_match = '';
      }

      $prod_result = $db->Execute("SELECT p.products_id, pd.products_name
                                   FROM " . TABLE_PRODUCTS . " p
                                     LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (p.products_id=pd.products_id)
                                   WHERE p.products_status='1'
                                     AND pd.language_id=" . $_SESSION['languages_id']);
      $prod_coverage = array();
      while (!$prod_result->EOF) {
        $cov_count = $db->Execute("SELECT COUNT(*) AS count FROM " . TABLE_SUPERTRACKER . " WHERE products_viewed LIKE '%*" . $prod_result->fields['products_id'] . "?%'" . $match_agent_string);
        if($cov_count->fields['count'] > 0) {
          $prod_coverage[$prod_result->fields['products_id']] = $cov_count->fields['count'];
          $prod_name[$prod_result->fields['products_id']] = $prod_result->fields['products_name'];
        }
        $prod_result->MoveNext();
      }
      arsort($prod_coverage);
      $tracker_data = array();
      $i = 0;
      foreach($prod_coverage as $products_id => $hits) {
        $tracker_data[$i]['prod_name'] = $prod_name[$products_id];
        $tracker_data[$i]['hits'] = $hits;
        $i++;
      }
?>
      <tr>
        <td class="dataTableContent">
<?php
echo zen_draw_form('filter_select', FILENAME_SUPERTRACKER, '', 'get', 'onchange="this.submit();"') . zen_hide_session_id() . zen_draw_hidden_field("report", "prod_coverage");
?>
             <?php echo TEXT_STRING_FILTER; // modified by azer ?>
             <input type="text" size="15" name="agent_match" value="<?php echo $agent_match;?>">
             <input type="submit" value = "Update">
           </form>
         </td>
      </tr>
<?php
      break;

    default:
      break;
  }
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo $title; ?></td>
            <td class="pageHeading" align="right"><?php echo zen_draw_separator('pixel_trans.gif', HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT); ?></td>
          </tr>
        </table></td>
      </tr>
<?php
if($tracker_data || $tracker_query_raw) {
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <?php foreach($headings as $h) {echo '<td class="dataTableHeadingContent">' . $h . '</td>';} ?>
              </tr>
<?php
  if(!isset($max_len) || $max_len == 0) $max_len = 512;
  $counter = 0;
  if($tracker_data) {
    foreach($tracker_data as $tracker) {
//      echo '<pre>';var_dump($tracker);echo '</pre>';
      $counter++;
?>
              <tr class="dataTableRow">
                <td class="dataTableContent"><?php echo $counter?></td>
<?php
      foreach($row_data as $r) {
        if (strlen($tracker[$r]) > $max_len) $tracker[$r] = substr($tracker[$r], 0, ($max_len-3)) . '...';
        echo '<td class="dataTableContent"' . $style_override . '>' . $tracker[$r] . '</td>';
      }
?>
              </tr>
<?php
    }
  }
  if($tracker_query_raw) {
    $tracker_query = $db->Execute($tracker_query_raw);
?>
<?php
    while (!$tracker_query->EOF) {
//    var_dump($counter, $tracker_query->fields);echo '<br />';
      $counter++;
?>
              <tr class="dataTableRow">
                <td class="dataTableContent"><?php echo $counter; ?></td>
<?php
      foreach($row_data as $r) {
        if (strlen($tracker_query->fields[$r]) > $max_len) $tracker_query->fields[$r] = substr($tracker_query->fields[$r], 0, ($max_len-3)) . '...';
        echo '<td class="dataTableContent"' . $style_override . '>' . $tracker_query->fields[$r] . '</td>';
      }
?>
              </tr>
<?php
      $tracker_query->MoveNext();
    }
  }
?>
            </table></td>
          </tr>
        </table></td>
      </tr>
    </table>
<?php
}
} //End if

//echo '<pre>';var_dump($_GET);echo '</pre>';
if (isset($_GET['report'])) {
  if ($_GET['report'] == 'last_ten') {

    if (isset($_GET['offset']))
      $offset = (int)$_GET['offset'];
    else
      $offset = 0;

    $where = array();

    if (isset($_GET['refer_match'])) {
      $where[] = "referrer LIKE '%" . zen_db_input($_GET['refer_match']) . "%'";
      $_GET['refer_match'] = zen_db_input($_GET['refer_match']);
    }

    if (isset($_GET['filter']))
      $filter = zen_db_input($_GET['filter']);
    else
      $filter = 'all';

    switch ($filter) {
      case 'bailed' :
        $where[] = "added_cart = 'true' AND completed_purchase = 'false'";
        break;
      case 'completed' :
        $where[] = "completed_purchase = 'true'";
        break;
      case 'all' :
      default:
        break;
    } // end switch

      if(sizeof($where) > 0) {
        $where = ' WHERE ' . implode(' AND ', $where);
      } else {
        $where = '';
      }
      $lt_query = "SELECT s.*, c.countries_name, s.country_code
                   FROM " . TABLE_SUPERTRACKER . " s
                     LEFT JOIN " . TABLE_COUNTRIES . " c ON (c.countries_iso_code_2 = s.country_code)" .
                   $where . "
                   ORDER BY last_click DESC
                   LIMIT " . $offset . ", 10";

      $lt_row = $db->Execute($lt_query);
?>
     <table width="100%" border=0 cellspacing=0 cellpadding=0>
       <tr>
         <td class="dataTableContent">
<?php
echo zen_draw_form('filter_select', FILENAME_SUPERTRACKER, '', 'get', 'onchange="this.submit();"') . zen_hide_session_id() . zen_draw_hidden_field("report", "last_ten");
?>
             Show : <select name="filter">
               <option value="all" <?php if ($filter == 'all') echo 'selected';?>><?php echo TEXT_SHOW_ALL; ?></option>
               <option value="bailed" <?php if ($filter == 'bailed') echo 'selected';?>><?php echo TEXT_BAILED_CARTS; ?></option>
               <option value="completed" <?php if ($filter == 'completed') echo 'selected';?>><?php echo TEXT_SUCCESSFUL_CHECKOUTS; ?></option>
             </select>
             <br /><?php echo TEXT_AND_OR_ENTER; ?><input type="text" size="15" name="refer_match" value="<?php echo $_GET['refer_match'];?>">
             <input type="submit" value = "Update">
           </form>
         </td>
      </tr>
    </table>

<?php
      while (!$lt_row->EOF) {
        if ($lt_row->fields['customer_id'] > 0) {
          $cust_row = $db->Execute("SELECT customers_firstname, customers_lastname, customers_email_address FROM " . TABLE_CUSTOMERS . " WHERE customers_id ='" . $lt_row->fields['customer_id'] . "'");
          $customer_name = $cust_row->fields['customers_firstname'] . ' ' . $cust_row->fields['customers_lastname'] . ' <' . $cust_row->fields['customers_email_address'] . '>';
        } else {
          $customer_name = "Guest";
        }

        if ($lt_row->fields['referrer'] == '')
          $referrer = 'Direct Access / Bookmark';
        else
          $referrer = '<a href="' . $lt_row->fields['referrer'] . '?' . $lt_row->fields['referrer_query_string'] . '" target="_blank">' . urldecode($lt_row->fields['referrer'] . '?' . $lt_row->fields['referrer_query_string']) . '</a>';

      echo '<table width="100%" border=0 cellspacing=0 cellpadding=1 style="border:1px solid #000;">';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_IP . '</b> <a href="http://whatismyipaddress.com/ip/' . $lt_row->fields['ip_address'] . '" target="_blank">' . $lt_row->fields['ip_address'] . '</a>' . ' (' . gethostbyaddr($lt_row->fields['ip_address']) . ') ' . zen_image(DIR_WS_IMAGES . 'flags/' . $lt_row->fields['country_code'] . '.gif') . ' ' . $lt_row->fields['countries_name'] . '</td></tr>';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_CUSTOMER_BROWSER_IDENT . '</b> ' . $lt_row->fields['browser_string'] . '</td></tr>';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_NAME . '</b> ' . $customer_name . '</td></tr>';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_REFFERED_BY . '</b> ' . $referrer . '</td></tr>';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_LANDING_PAGE . '</b> ' . '<a href="' . $lt_row->fields['landing_page'] . '" target="_blank" title="' . $lt_row->fields['landing_page_name'] . '">' . $lt_row->fields['landing_page_name'] . '</a>' . '</td></tr>';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_LAST_PAGE_VIEWED . '</b> ' . '<a href="' . $lt_row->fields['exit_page'] . '" target="_blank" title="' . $lt_row->fields['exit_page_name'] . '">' . $lt_row->fields['exit_page_name'] . '</a>' . '</td></tr>';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_TIME_ARRIVED . '</b> ' . zen_datetime_short($lt_row->fields['time_arrived']) . '</td></tr>';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_LAST_CLICK . '</b> ' . zen_datetime_short($lt_row->fields['last_click']) . '</td></tr>';

      $time_on_site = strtotime($lt_row->fields['last_click']) - strtotime($lt_row->fields['time_arrived']);
      $hours_on_site = floor($time_on_site /3600);
      $minutes_on_site = floor( ($time_on_site - ($hours_on_site*3600))  / 60);
      $seconds_on_site = $time_on_site - ($hours_on_site *3600) - ($minutes_on_site * 60);
      $time_on_site = $hours_on_site . 'hrs ' . $minutes_on_site . 'mins ' . $seconds_on_site . ' seconds';

      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_TIME_ON_SITE . '</b> ' . $time_on_site . '</td></tr>';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_NUMBER_OF_CLICKS . '</b> ' . $lt_row->fields['num_clicks'] . '</td></tr>';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_ADDED_CART . '</b> ' . $lt_row->fields['added_cart'] . '</td></tr>';
      echo '<tr><td class="dataTableContent"><b>' . TABLE_TEXT_COMPLETED_PURCHASE . '</b> ' . $lt_row->fields['completed_purchase'] . '</td></tr>';

      if ($lt_row->fields['completed_purchase'] == 'true') {
        $order_result = $db->Execute("SELECT ot.text AS order_total FROM " . TABLE_ORDERS . " o, " . TABLE_ORDERS_TOTAL . " ot WHERE o.orders_id=ot.orders_id AND o.orders_id = '" . $lt_row->fields['order_id'] . "' AND ot.class='ot_total'");
         if ($order_result->RecordCount() > 0) {
           echo '<tr><td class="dataTableContent">' . TABLE_TEXT_ORDER_VALUE . $order_result->fields['order_total'] . '</td></tr>';
         }
      }

      $categories_viewed = unserialize($lt_row->fields['categories_viewed']);
      if (!empty($categories_viewed)) {
        $cat_string = '';
        foreach($categories_viewed as $cat_id => $val) {
          $cat_row = $db->Execute("SELECT cd.categories_name FROM " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd WHERE c.categories_id=cd.categories_id AND c.categories_id='" . $cat_id . "' AND cd.language_id=" . $_SESSION['languages_id']);
//          $cat_string .= $cat_row->fields['categories_name'] . ', ';
          $cat_string .= '<a href="' . zen_catalog_href_link(FILENAME_DEFAULT, 'cPath=' . $cat_id) . '" target="_blank" title="' . $cat_row->fields['categories_name'] . '">' . $cat_row->fields['categories_name'] . '</a>' . ', ';
        }
        $cat_string = rtrim($cat_string, ', ');
//        var_dump($need_tr, $col, THUMB_COLS);echo '<br />';
        echo '<tr><td class="dataTableContent"><strong>' . TABLE_TEXT_CATEGORIES . '</strong>' . $cat_string . '</td></tr>';
      }

define('THUMB_IMAGE_WIDTH', SMALL_IMAGE_WIDTH/2);
define('THUMB_IMAGE_HEIGHT', SMALL_IMAGE_HEIGHT/2);
define('THUMB_COLS', 9);
define('THUMB_STRLEN', 60);
      if ($lt_row->fields['products_viewed'] != '') {
        echo '<tr><td class="dataTableContent"><strong>' . TABLE_TEXT_PRODUCTS . ' </strong><table cellspacing=0 cellpadding=2 border=0 width="100%">';
        $prod_view_array = explode ('*',$lt_row->fields['products_viewed']);
        $col = 0;
        foreach($prod_view_array as $key => $product_id) {
          $product_id = rtrim($product_id, '?');
          if ($product_id != '') {
            if($col == 0) {
              $need_tr = true;
              echo '<tr>' . "\n";
              $col = THUMB_COLS;
            }
            $products_name = zen_get_products_name($product_id);
            $products_img = zen_get_products_image($product_id);
            if(!is_file(DIR_FS_CATALOG_IMAGES . $products_img)) $products_img = 'no_picture.gif';
            echo '<td align="center" style="border:1px solid #000; vertical-align: top;" width="' . (THUMB_IMAGE_WIDTH+6) . 'px">' . zen_image(DIR_WS_CATALOG_IMAGES . $products_img, $products_name, THUMB_IMAGE_WIDTH, THUMB_IMAGE_HEIGHT) . '<br />' . (strlen($products_name) < THUMB_STRLEN ? $products_name : substr($products_name, 0, THUMB_STRLEN).'...') . '</td>' . "\n";
            if($col == 0 && $need_tr) {
              echo '</tr>' . "\n";
              $need_tr = false;
            }
            $col--;
          }
        }
        if($need_tr) {
          if($col > 0) echo '<td style="border:none;" width="' . (THUMB_IMAGE_WIDTH+6)*$col . 'px" colspan="' . $col . '">';
          echo '</tr>';
        }
        echo '</table></td></tr>';
      }

      $cart_contents = unserialize($lt_row->fields['cart_contents']);
      if (!empty($cart_contents)) {
        echo '<tr><td class="dataTableContent"><strong>' . TABLE_TEXT_CUSTOMERS_CART . '(value=' . $currencies->format($lt_row->fields['cart_total']) . ') : </strong><table cellspacing=0 cellpadding=0 border=0><tr>';
        foreach($cart_contents as $product_id => $qty_array) {
            $products_name = zen_get_products_name($product_id);
            echo '<td><table cellspacing=0 cellpadding=2 border=0 align="center" style="border:1px solid #000;"><tr><td align="center">' . zen_image(DIR_WS_CATALOG_IMAGES . zen_get_products_image($product_id), $products_name, SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT) . '</td></tr><tr><td class="dataTableContent" align="center">' . $products_name . '</td></tr><tr><td class="dataTableContent">' . TABLE_TEXT_QUANTITY . $qty_array['qty'] . '</td></tr></table></td>';
        }
        echo '</tr></table></td></tr>';
      }

      echo '</table>';
      $lt_row->MoveNext();
    }//End While
?>
<strong><a href="<?php echo zen_href_link(FILENAME_SUPERTRACKER, 'report=last_ten&offset=' . ($offset + 10) . '&filter=' . $filter . '&refer_match=' . $_GET['refer_match']); ?>"><?php echo TABLE_TEXT_NEXT_TEN_RESULTS; ?></a></strong>
<?php
    }//End "Last Ten" Report

   if ($_GET['report'] == 'ppc_summary') {
     echo '<table width="100%" border=0 cellspacing=0 cellpadding=5 style="border:1px solid #000;">';
    foreach($ppc as $ref_code => $details) {
      $scheme_name = $details['title'];
      $keywords = $details['keywords'];

      $ppc_result = $db->Execute("SELECT * FROM " . TABLE_SUPERTRACKER . " WHERE landing_page LIKE '%ref=" . $ref_code . "%'");
      $ppc_num_refs = $ppc_result->RecordCount();
      echo '<tr><td style="font-weight:bold;text-decoration:underline;">' . $scheme_name . ' - Total Referrals ' . $ppc_num_refs . '</td></tr>';

      if ($keywords != '') {
        $keyword_array = explode(',',$keywords);
        foreach($keyword_array as $key => $val) {
          $colon_pos = strpos ($val, ':');
          $keyword_code = substr($val, 0, $colon_pos);
          $keyword_friendly_name = substr($val, $colon_pos+1, strlen($val)-$colon_pos);
          $ppc_row = $db->Execute("SELECT *, count(*) as count, AVG(num_clicks) as ave_clicks, AVG(UNIX_TIMESTAMP(last_click) - UNIX_TIMESTAMP(time_arrived))/60 as ave_time FROM " . TABLE_SUPERTRACKER . " WHERE landing_page LIKE '%ref=" . $ref_code . "&keyw=" . $keyword_code . "%' GROUP BY landing_page");
          $ppc_key_refs = $ppc_row->fields['count'];
          echo '<tr><td>' . $keyword_friendly_name . ' : ' . $ppc_key_refs . TABLE_TEXT_REFERRALS_AVERAGE_TIME  . $ppc_row->fields['ave_time'] . TABLE_TEXT_MINS_AVERAGE_CLICKS  . $ppc_row->fields['ave_clicks'] . '</td></tr>';
        }
      }
    }
    echo '</table>';

   }//End PPC Summary Report

}
?>

    </td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>