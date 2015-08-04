<?php

/**
 * @Project NUKEVIET 4.x
 * @Author VINADES.,JSC (contact@vinades.vn)
 * @Copyright (C) 2014 VINADES.,JSC. All rights reserved
 * @License GNU/GPL version 2 or any later version
 * @Createdate 3-6-2010 0:30
 */

if( ! defined( 'NV_IS_MOD_DOWNLOAD' ) ) die( 'Stop!!!' );

$session_files = $nv_Request->get_string( 'session_files', 'session', '' );
if( empty( $session_files ) )
{
	die( 'Wrong URL' );
}

$session_files = unserialize( $session_files );

if( $nv_Request->isset_request( 'code', 'get' ) )
{
	$code = $nv_Request->get_string( 'code', 'get', '' );

	if( empty( $code ) or ! preg_match( "/^([a-z0-9]{32})$/i", $code ) or ! isset( $session_files['linkdirect'][$code] ) )
	{
		die( 'Wrong URL' );
	}

	$sql = 'UPDATE ' . NV_PREFIXLANG . '_' . $module_data . ' SET download_hits=download_hits+1 WHERE id=' . intval( $session_files['linkdirect'][$code]['id'] );
	$db->query( $sql );

	$content = "<br /><img border=\"0\" src=\"" . NV_BASE_SITEURL . NV_ASSETS_DIR . "images/load_bar.gif\"><br /><br />\n";
	$content .= sprintf( $lang_module['download_wait2'], $session_files['linkdirect'][$code]['link'] );
	$content .= "<meta http-equiv=\"refresh\" content=\"5;url=" . $session_files['linkdirect'][$code]['link'] . "\" />";

	nv_info_die( $lang_module['download_detail'], $lang_module['download_wait'], $content );
	die();
}


$filename = $nv_Request->get_string( 'filename', 'get', '' );
if( empty( $filename ) or ! isset( $session_files['fileupload'][$filename] ) )
{
	die( 'Wrong URL' );
}

if( ! file_exists( $session_files['fileupload'][$filename]['src'] ) )
{
	die( 'Wrong URL' );
}

if( ! isset( $session_files['fileupload'][$filename]['id'] ) )
{
	die( 'Wrong URL' );
}

$upload_dir = 'files';
$is_zip = false;
$is_resume = false;
$max_speed = 0;

$filepdf = $nv_Request->get_int( 'filepdf', 'get', 0 );
if( $filepdf == 1 )
{
	$html = theme_viewpdf( $filename );
	die($html);
}
elseif( empty( $filepdf ) )
{
	$sql = 'UPDATE ' . NV_PREFIXLANG . '_' . $module_data . ' SET download_hits=download_hits+1 WHERE id=' . intval( $session_files['fileupload'][$filename]['id'] );
	$db->query( $sql );
}

$sql = "SELECT config_name, config_value FROM " . NV_PREFIXLANG . "_" . $module_data . "_config WHERE config_name='upload_dir' OR config_name='is_zip' OR config_name='is_resume' OR config_name='max_speed'";
$result = $db->query( $sql );
while( $row = $result->fetch() )
{
	if( $row['config_name'] == 'upload_dir' )
	{
		$upload_dir = $row['config_value'];
	}
	elseif( $row['config_name'] == 'is_zip' )
	{
		$is_zip = ( $filepdf == 2 ) ? false : ( bool )$row['config_value'];
	}
	elseif( $row['config_name'] == 'is_resume' )
	{
		$is_resume = ( bool )$row['config_value'];
	}
	elseif( $row['config_name'] == 'max_speed' )
	{
		$max_speed = ( int )$row['config_value'];
	}
}
$file_src = $session_files['fileupload'][$filename]['src'];
$file_basename = $filename;
$directory = NV_UPLOADS_REAL_DIR;

if( $is_zip )
{
	$upload_dir = NV_UPLOADS_REAL_DIR . '/' . $module_upload . '/' . $upload_dir;
	$subfile = nv_pathinfo_filename( $filename );
	$tem_file = NV_ROOTDIR . '/' . NV_TEMP_DIR . '/' . NV_TEMPNAM_PREFIX . $subfile;

	$file_exists = file_exists( $tem_file );

	if( $file_exists and filemtime( $tem_file ) > NV_CURRENTTIME - 600 )
	{
		$file_src = $tem_file;
		$file_basename = $subfile . '.zip';
		$directory = NV_ROOTDIR . '/' . NV_TEMP_DIR;
	}
	else
	{
		if( $file_exists )
		{
			@nv_deletefile( $tem_file );
		}

		$zip = new PclZip( $tem_file );

		$zip->add( $file_src, PCLZIP_OPT_REMOVE_PATH, $upload_dir );

		if( isset( $global_config['site_logo'] ) and ! empty( $global_config['site_logo'] ) and file_exists( NV_ROOTDIR . '/' . $global_config['site_logo'] ) )
		{
			$paths = explode( '/', $global_config['site_logo'] );
			array_pop( $paths );
			$paths = implode( '/', $paths );
			$zip->add( NV_ROOTDIR . '/' . $global_config['site_logo'], PCLZIP_OPT_REMOVE_PATH, NV_ROOTDIR . '/' . $paths );
		}

		if( file_exists( NV_ROOTDIR . '/' . NV_DATADIR . '/README.txt' ) )
		{
			$zip->add( NV_ROOTDIR . '/' . NV_DATADIR . '/README.txt', PCLZIP_OPT_REMOVE_PATH, NV_ROOTDIR . '/' . NV_DATADIR );
		}

		if( file_exists( $tem_file ) )
		{
			$file_src = $tem_file;
			$file_basename = $subfile . '.zip';
			$directory = NV_ROOTDIR . '/' . NV_TEMP_DIR;
		}
	}
}

$download = new download( $file_src, $directory, $file_basename, $is_resume, $max_speed );
if( $is_zip )
{
	$mtime = ( $mtime = filemtime( $session_files['fileupload'][$filename]['src'] ) ) > 0 ? $mtime : NV_CURRENTTIME;
	$download->set_property( 'mtime', $mtime );
}
$download->download_file();
exit();