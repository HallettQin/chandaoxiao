<?php
//cgxlm
namespace Symfony\Component\Translation\Loader;

class MoFileLoader extends FileLoader
{
	const MO_LITTLE_ENDIAN_MAGIC = 2500072158;
	const MO_BIG_ENDIAN_MAGIC = 3725722773;
	const MO_HEADER_SIZE = 28;

	protected function loadResource($resource)
	{
		$stream = fopen($resource, 'r');
		$stat = fstat($stream);

		if ($stat['size'] < self::MO_HEADER_SIZE) {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException('MO stream content has an invalid format.');
		}

		$magic = unpack('V1', fread($stream, 4));
		$magic = hexdec(substr(dechex(current($magic)), -8));

		if ($magic == self::MO_LITTLE_ENDIAN_MAGIC) {
			$isBigEndian = false;
		}
		else if ($magic == self::MO_BIG_ENDIAN_MAGIC) {
			$isBigEndian = true;
		}
		else {
			throw new \Symfony\Component\Translation\Exception\InvalidResourceException('MO stream content has an invalid format.');
		}

		$this->readLong($stream, $isBigEndian);
		$count = $this->readLong($stream, $isBigEndian);
		$offsetId = $this->readLong($stream, $isBigEndian);
		$offsetTranslated = $this->readLong($stream, $isBigEndian);
		$this->readLong($stream, $isBigEndian);
		$this->readLong($stream, $isBigEndian);
		$messages = array();

		for ($i = 0; $i < $count; ++$i) {
			$pluralId = null;
			$translated = null;
			fseek($stream, $offsetId + ($i * 8));
			$length = $this->readLong($stream, $isBigEndian);
			$offset = $this->readLong($stream, $isBigEndian);

			if ($length < 1) {
				continue;
			}

			fseek($stream, $offset);
			$singularId = fread($stream, $length);

			if (strpos($singularId, "\x00") !== false) {
				list($singularId, $pluralId) = explode("\x00", $singularId);
			}

			fseek($stream, $offsetTranslated + ($i * 8));
			$length = $this->readLong($stream, $isBigEndian);
			$offset = $this->readLong($stream, $isBigEndian);

			if ($length < 1) {
				continue;
			}

			fseek($stream, $offset);
			$translated = fread($stream, $length);

			if (strpos($translated, "\x00") !== false) {
				$translated = explode("\x00", $translated);
			}

			$ids = array('singular' => $singularId, 'plural' => $pluralId);
			$item = compact('ids', 'translated');

			if (is_array($item['translated'])) {
				$messages[$item['ids']['singular']] = stripcslashes($item['translated'][0]);

				if (isset($item['ids']['plural'])) {
					$plurals = array();

					foreach ($item['translated'] as $plural => $translated) {
						$plurals[] = sprintf('{%d} %s', $plural, $translated);
					}

					$messages[$item['ids']['plural']] = stripcslashes(implode('|', $plurals));
				}
			}
			else if (!empty($item['ids']['singular'])) {
				$messages[$item['ids']['singular']] = stripcslashes($item['translated']);
			}
		}

		fclose($stream);
		return array_filter($messages);
	}

	private function readLong($stream, $isBigEndian)
	{
		$result = unpack($isBigEndian ? 'N1' : 'V1', fread($stream, 4));
		$result = current($result);
		return (int) substr($result, -8);
	}
}

?>
