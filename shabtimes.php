<?php
/*
Plugin Name: Shabbat Times
Plugin URI: https://github.com/sirspamsalot/Shabbat_Times_-_Wordpress
Description: Customizable shabbat times widget
Version: 0.2
Authors: Yair Silbermintz & Shalom Silbermintz
Author URI: http://Shalom.Silbermintz.com
License: DBAB - http://www.dbad-license.org/
*/

//define plugins defaults
$template = '
<ul id="ShabbosTimes">
    <h3>Times for parashat [parsha]</h3>
    <li>Candle lighting: [candles]</li>
    <li>Havdalah (72 min): [havdalah]</li>
</ul>
';
$defaults = array('title' => 'Local shabbos times', 'zip' => 87187, 'offset' => 72, 'template' => $template, 'timeFormat' => 'g:ia l, F jS');


//grap times from the api and return them as an array in PHP friendly formats
function getTimes($zip, $havdalahoffset) {

    //assemble API URL
    $url = "http://www.hebcal.com/hebcal/?v=1;cfg=json;nh=on;nx=on;year=".date("Y").";month=".date("n").";ss=on;mf=on;c=on;zip=$zip;m=$havdalahoffset;s=on"; //assemble API url/Call
    //grab from API using cURL
    $ch = curl_init ($url) ;
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1) ;
    $json = curl_exec ($ch) ;
    curl_close ($ch) ;
    $results1 = json_decode($json)->items; // Decode JSON API

    if(date("n") == 12)    {
        $year = date("Y") + 1;
        $url = "http://www.hebcal.com/hebcal/?v=1;cfg=json;nh=on;nx=on;year=$year;month=1;ss=on;mf=on;c=on;zip=$zip;m=$havdalahoffset;s=on"; //assemble API url/Call
    }
    else    {
        $month = date("n") +1;
        $url = "http://www.hebcal.com/hebcal/?v=1;cfg=json;nh=on;nx=on;year=".date("Y").";month=$month;ss=on;mf=on;c=on;zip=$zip;m=$havdalahoffset;s=on"; //assemble API url/Call
    }
    //grab from API using cURL
    $ch = curl_init ($url) ;
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1) ;
    $json = curl_exec ($ch) ;
    curl_close ($ch) ;
    $results2 = json_decode($json)->items; // Decode JSON API
    
    $results = array_merge($results1, $results2);
    
    $i = 0;
    //loop through results until we get to todays times
    while(strtotime($results[$i]->date) < time()-(24*60*60) || $results[$i]->category != "candles") {
        $i++;
    }
    
    //initialize assoc array of shabbos times
    $times = array();
    $times["candles"] = "Error";
    $times["parsha"] = "Error";
    $times["havdalah"] = "Error";
    
    //api returns the results we need as 3 individual objects, so we loop through the next 3 attempting to get the times we need.
    for($j=0;$j<3;$j++)    {
        $index = $i+$j;
        if($results[$index]->category == "candles")  {
            //if its candlelighting, grab candlelighting time and convert to timestamp
            $times["candles"] = strtotime($results[$index]->date);
        }
        elseif($results[$index]->category == "parashat")  {
            //if its the parsha name, 
            $times["parsha"] = substr($results[$index]->title, 8);
        }
        elseif($results[$index]->category == "havdalah")  {
            //if its havdalah, grab havdalah time and convert to timestamp
            $times["havdalah"] = strtotime($results[$index]->date);
        }
        elseif($results[$index]->category == "holiday")  {
            //if its havdalah, grab havdalah time and convert to timestamp
            $times["havdalah"] = strtotime($results[$index+1]->date);
            //die(print_r($results[$index+1]));
        }
        
    }
    
    return $times;
}

function widget_shabbosTimes($args) {
    extract($args); //grab template and wp stuff
    $shabbosTimes_options = get_option('shabbosTimes_options', $GLOBALS['defaults']); //grab settings or defaults
    
    echo $before_widget; //outputs template stuff for before a widget
    echo $before_title . $shabbosTimes_options['title'] . $after_title; //outputs a widget title within templates tags
    
    $times = getTimes($shabbosTimes_options['zip'], $shabbosTimes_options['offset']); //grab times
    
    $zoneoffset = $shabbosTimes_options['ZoneOffset'] * 60 * 60; //convert from hours to seconds.
        //later, we will subtract these seconds from the unix time to display the time in the correct time zone
        //a bit of an ugly method, but it works.
    
    //insert data into user defined template
    if($times["parsha"] != "Error")$out = str_replace('[parsha]', $times["parsha"], $shabbosTimes_options['template']);
    if($times["candles"] != "Error")$out = str_replace('[candles]', date($shabbosTimes_options['timeFormat'], $times["candles"] - $zoneoffset), $out);
    if($times["havdalah"] != "Error")    $out = str_replace('[havdalah]', date($shabbosTimes_options['timeFormat'], $times["havdalah"] - $zoneoffset), $out);
    echo $out;
    
    echo "<small>Powered by <a href=\"http://www.hebcal.com/shabbat/?geo=zip;zip=$zip;m=55\">Hebcal</a></small>"; //credit where credit is due
    echo $after_widget; //more template stuff
    
}

//widget init function
function shabbosTimes_init()  {
    register_sidebar_widget(__('Shabbos Times'), 'widget_shabbosTimes');
}

//hooks into wp
add_action("plugins_loaded", "shabbosTimes_init");
add_action('admin_menu', 'plugin_admin_add_page');
add_action('admin_init', 'plugin_admin_init');

//add our page to the settings submenu
function plugin_admin_add_page() {
    add_options_page('Settings for shabbos times plugin', 'Shabbos times plugin', 'manage_options', 'shabbosTimes', 'shabbosTimes_settings');
}

// display the admin options page
function shabbosTimes_settings() {    ?>
<div>
    <h2>Settings for the shabbos times plugin</h2>
    Use these settings to customize the shabbos times widget to your liking.
    <form action="options.php" method="post">
        <?php settings_fields('shabbosTimes_options'); ?>
        <?php do_settings_sections('shabbosTimes'); ?>
        <br />
        <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
    </form>
</div>

<?php
}

// add the admin settings and such
function plugin_admin_init(){
    register_setting( 'shabbosTimes_options', 'shabbosTimes_options', 'shabbosTimes_options_validate' );
    add_settings_section('shabbosTimes', '', 'shabbosTimes_section_text', 'shabbosTimes');
    add_settings_field('shabbosTimes_Title', 'Title to display in sidebar:', 'shabbosTimes_Title', 'shabbosTimes', 'shabbosTimes');
    add_settings_field('shabbosTimes_Zip', 'Zip code for times:', 'shabbosTimes_Zip', 'shabbosTimes', 'shabbosTimes');
    add_settings_field('shabbosTimes_Offset', 'Number of minutes after sunset for havdalah:', 'shabbosTimes_Offset', 'shabbosTimes', 'shabbosTimes');
    add_settings_field('shabbosTimes_Template', 'Template for sidebar HTML:', 'shabbosTimes_Template', 'shabbosTimes', 'shabbosTimes');
    add_settings_field('shabbosTimes_TimeFormat', 'Time format string:', 'shabbosTimes_TimeFormat', 'shabbosTimes', 'shabbosTimes');
    add_settings_field('shabbosTimes_ZoneOffset', 'UTC offset in hours:', 'shabbosTimes_ZoneOffset', 'shabbosTimes', 'shabbosTimes');
}

//display admin section title
function shabbosTimes_section_text() {
    //dont want a title here
}

//display inputs for setting fields
function shabbosTimes_Title() {
    $shabbosTimes_options = get_option('shabbosTimes_options', $GLOBALS['defaults']);
    echo "<input id='shabbosTimes_Title' name='shabbosTimes_options[title]' size='40' type='text' value='{$shabbosTimes_options['title']}' />";
}
function shabbosTimes_Zip() {
    $shabbosTimes_options = get_option('shabbosTimes_options', $GLOBALS['defaults']);
    echo "<input id='shabbosTimes_Zip' name='shabbosTimes_options[zip]' size='5' type='text' value='{$shabbosTimes_options['zip']}' />";
}
function shabbosTimes_Offset() {
    $shabbosTimes_options = get_option('shabbosTimes_options', $GLOBALS['defaults']);
    echo "<input id='shabbosTimes_Offset' name='shabbosTimes_options[offset]' size='3' type='text' value='{$shabbosTimes_options['offset']}' />";
}
function shabbosTimes_Template() {
    $shabbosTimes_options = get_option('shabbosTimes_options', $GLOBALS['defaults']);
    ?>
    <p style="width: 700px">To customize the widget output, simply enter the desired HTML below. Use [parsha], [candles], & [havdalah] to indicate where you want the relevant data.</p>
    <textarea  id='shabbosTimes_Template' name='shabbosTimes_options[template]' rows="20" cols="100"> <?php echo $shabbosTimes_options['template']; ?> </textarea>
    <?php
}
function shabbosTimes_TimeFormat() {
    $shabbosTimes_options = get_option('shabbosTimes_options', $GLOBALS['defaults']);
    echo "<p style=\"width: 700px\">This controls the formatting of the havdalah and candle lighting times. It uses a string to define the format for the PHP date function. For more information & examples, please read the <a href=\"http://php.net/manual/en/function.date.php\">PHP Date()</a> documentation.</p>";
    echo "<input id='shabbosTimes_TimeFormat' name='shabbosTimes_options[timeFormat]' size='60' type='text' value='{$shabbosTimes_options['timeFormat']}' />";
}
function shabbosTimes_ZoneOffset() {
    $shabbosTimes_options = get_option('shabbosTimes_options', $GLOBALS['defaults']);
    echo "<input id='shabbosTimes_ZoneOffset' name='shabbosTimes_options[ZoneOffset]' size='2' type='text' value='{$shabbosTimes_options['ZoneOffset']}' />";
}

//validate/clean all settings
function shabbosTimes_options_validate($input) {
    $newinput['title'] = trim($input['title']); //allow anything admin wants for title
    $newinput['zip'] = trim($input['zip']);
    //check zip is an int
    $newinput['offset'] = trim($input['offset']);
    //check offset is an int
    $newinput['template'] = trim($input['template']); //allow watever the admin wants for template
    $newinput['timeFormat'] = trim($input['timeFormat']); //allow watever the admin wants for timeFormat
	$newinput['ZoneOffset'] = trim($input['ZoneOffset']);
    //check offset is an int
    
    return $newinput;
}
?>