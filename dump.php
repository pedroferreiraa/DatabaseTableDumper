#!/usr/bin/php
<?php

function help($program)
{
	echo "Usage: $program [options]\n";
	echo "  -z\t\tZip all files\n";
	die();
}

function make_connection()
{
	$host = 'localhost'; 		/* Change this! */
	$username = 'user';		/* Change this! */
	$password = 'user';		/* Change this! */
	$database = 'test';		/* Change this! */

	$sql = new mysqli($host, $username, $password, $database);

	if ($sql->connect_error)
		die("Connection failed: " . $sql->connect_error);

	return $sql;
}

function get_all_tables($mysqli)
{
	$query = $mysqli->query("SHOW TABLES");

	if (!$query)
		die("Cannot retrieve tables. " . $mysqli->error . " Exiting...");

	$tables = [];

	while ($row = $query->fetch_row())
		$tables[] = $row[0];

	return $tables;
}

function create_and_move_directory()
{
	$dir_name = time();

	if (!is_dir($dir_name))
		if (!mkdir($dir_name, 0700))
			die("Cannot write in this directory");

	chdir($dir_name);

	return $dir_name;
}

function save_table_to_csv($mysqli, $table_name)
{
	$query = $mysqli->query("SELECT * FROM $table_name");

	if (!$query) { return 1; }

	if ($query->num_rows > 0) {
		$fptr = fopen("$table_name.csv", "w");
		$columns = [];
		$row = $query->fetch_assoc();

		foreach ($row as $i => $j)
			$columns[] = $i;

		fputcsv($fptr, $columns);

		$query->data_seek(0);

		while ($row = $query->fetch_assoc())
			fputcsv($fptr, $row);

		fclose($fptr);
		$query->free();

		return 0;
	}

	return 1;
}

function zip_all($dir_name)
{
	$password = "";
	$alphabet = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$zip_file = new ZipArchive();
	$zip_file_name = "../$dir_name.zip";

	for ($i = 0; $i < rand(8, 32); $i++)
		$password .= $alphabet[rand(0, strlen($alphabet) - 1)];

	if ($zip_file->open($zip_file_name, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
		$zip_file->setPassword($password);
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("."));

		foreach ($files as $name => $file) {
			if (!$file->isDir()) {
				$zip_file->addFile($file->getRealPath(), $file);;
			}
		}
	}

	$zip_file->close();

	return $password;
}

if ($argc == 2 && $argv[1] === "--help")
	help(basename($argv[0]));

$mysqli = make_connection();
$tables = get_all_tables($mysqli);

if (!$tables) { die("No tables in this database"); }

$dir_name = create_and_move_directory();

foreach ($tables as $table_name) {
	if (save_table_to_csv($mysqli, $table_name))
		echo "Cannot get this table. Go next table\n";
	else
		echo "Created $table_name.csv successfully\n";
}

if ($argc == 2 && $argv[1] === "-z")
	echo "\n\nPASSWORD: " . zip_all($dir_name) . "\n\n";

$mysqli->close();
