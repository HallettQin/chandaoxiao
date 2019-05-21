<?php
//cgxlm
namespace Illuminate\Contracts\Filesystem;

interface Filesystem
{
	const VISIBILITY_PUBLIC = 'public';
	const VISIBILITY_PRIVATE = 'private';

	public function exists($path);

	public function get($path);

	public function put($path, $contents, $visibility = NULL);

	public function getVisibility($path);

	public function setVisibility($path, $visibility);

	public function prepend($path, $data);

	public function append($path, $data);

	public function delete($paths);

	public function copy($from, $to);

	public function move($from, $to);

	public function size($path);

	public function lastModified($path);

	public function files($directory = NULL, $recursive = false);

	public function allFiles($directory = NULL);

	public function directories($directory = NULL, $recursive = false);

	public function allDirectories($directory = NULL);

	public function makeDirectory($path);

	public function deleteDirectory($directory);
}


?>
