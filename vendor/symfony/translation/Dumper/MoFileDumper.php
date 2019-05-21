<?php
//cgxlm
namespace Symfony\Component\Translation\Dumper;

class MoFileDumper extends FileDumper
{
	public function formatCatalogue(\Symfony\Component\Translation\MessageCatalogue $messages, $domain, array $options = array())
	{
		$sources = $targets = $sourceOffsets = $targetOffsets = '';
		$offsets = array();
		$size = 0;

		foreach ($messages->all($domain) as $source => $target) {
			$offsets[] = array_map('strlen', array($sources, $source, $targets, $target));
			$sources .= "\x00" . $source;
			$targets .= "\x00" . $target;
			++$size;
		}

		$header = array('magicNumber' => \Symfony\Component\Translation\Loader\MoFileLoader::MO_LITTLE_ENDIAN_MAGIC, 'formatRevision' => 0, 'count' => $size, 'offsetId' => \Symfony\Component\Translation\Loader\MoFileLoader::MO_HEADER_SIZE, 'offsetTranslated' => \Symfony\Component\Translation\Loader\MoFileLoader::MO_HEADER_SIZE + (8 * $size), 'sizeHashes' => 0, 'offsetHashes' => \Symfony\Component\Translation\Loader\MoFileLoader::MO_HEADER_SIZE + (16 * $size));
		$sourcesSize = strlen($sources);
		$sourcesStart = $header['offsetHashes'] + 1;

		foreach ($offsets as $offset) {
			$sourceOffsets .= $this->writeLong($offset[1]) . $this->writeLong($offset[0] + $sourcesStart);
			$targetOffsets .= $this->writeLong($offset[3]) . $this->writeLong($offset[2] + $sourcesStart + $sourcesSize);
		}

		$output = implode(array_map(array($this, 'writeLong'), $header)) . $sourceOffsets . $targetOffsets . $sources . $targets;
		return $output;
	}

	protected function getExtension()
	{
		return 'mo';
	}

	private function writeLong($str)
	{
		return pack('V*', $str);
	}
}

?>
