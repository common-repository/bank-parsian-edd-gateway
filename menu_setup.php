<?php 

add_action('admin_menu', 'bpeddg_setMenu');


		
function bpeddg_setMenu(  )
{

	add_menu_page('<span>بانک پارسیان</span>', '<span>بانک پارسیان</span>', 'activate_plugins', "parsian_bank_gate", 'bpeddg_load_inteface', plugin_dir_url( __FILE__ ).'/images/icon.png'); 
	add_submenu_page("parsian_bank_gate", '<span>درباره ما</span>', '<span>درباره ما</span>', 'activate_plugins', "parsian_bank_gate_about", "bpeddg_load_about");
	
}


function bpeddg_load_inteface(  )
{
	include dirname(__file__)."/parsian.php";
}
function bpeddg_load_about(  )
{
	include dirname(__file__)."/about.php";
}
function bpeddg_load_news(  )
{
	include dirname(__file__)."/news.php";
}