<?php
declare(strict_types=1);
namespace gimle\sql;

use const gimle\ENV_MODE;
use const gimle\ENV_WEB;
use const gimle\ENV_CLI;
use function gimle\colorize;

trait Explain
{
	/**
	 * Explain the performed queries.
	 *
	 * @return string
	 */
	public function explain (): string
	{
		$textcolor = colorize('', 'black');
		$errstyle = 'style="' . colorize('', 'error') . '"';
		$errstyle = 'style="color: #c00;"';
		$textstyle = '';
		if ($textcolor !== '') {
			$textstyle = ' style="' . $textcolor . '"';
			$textcolor = ' ' . $textcolor;
		}
		$return = '';
		$sqlnum = 0;
		$sqltime = 0;
		$duplicates = [];
		foreach ($this->queryCache as $query) {
			$duplicates[] = $query['query'];
			$sqltime += $query['time'];
			$sqlnum++;
			$query['time'] = colorize((string) $query['time'], 'range:{"type": "alert", "max":0.09, "value":' . str_replace(',', '.', $query['time']) . '}');
			if (ENV_MODE & ENV_WEB) {
				$return .= '<table border="1" style="font-size: 12px; width: 100%; border-collapse: collapse;">';
				$return .= '<tr><td colspan="12" style="font-family: monospace; font-size: 11px;' . $textcolor . '">' . $query['query'] . '</td></tr>';
				$return .= '<tr><td colspan="12"' . $textstyle . '>Affected rows: ' . $query['rows'] . ', Query Time: ' . $query['time'] . '</td></tr><tr>';
			}
			else {
				$return .= colorize($query['query'], 'black', $background) . "\n";
				$return .= colorize('Affected rows: ' . $query['rows'] . ', Query Time: ' . $query['time'], 'black', $background) . "\n";
			}
			$temp = '';
			if (($query['error'] === false) && (preg_match('/^SELECT/i', $query['query']) > 0)) {
				$charcount = [];
				$fieldsarray = [];
				$res = parent::query('EXPLAIN ' . $query['query']);
				$fields = $res->fetch_fields();
				foreach ($fields as $field) {
					if (ENV_MODE & ENV_WEB) {
						$return .= '<th' . $textstyle . '>' . $field->name . '</th>';
					}
					else {
						$fieldsarray[] = $field->name;
					}
				}
				if (ENV_MODE & ENV_WEB) {
					$return .= '</tr>';
				}
				$rowarray = [];
				while ($row = $res->fetch_assoc()) {
					$subrowarray = [];
					$i = 0;
					foreach ($row as $key => $value) {
						if (ENV_MODE & ENV_CLI) {
							$thiscount = (($value === null) ? 4 : strlen($value));
							if (isset($charcount[$key])) {
								$charcount[$key] = max($thiscount, $charcount[$key]);
							}
							else {
								$charcount[$key] = max($thiscount, strlen($fieldsarray[$i]));
							}
							$subrowarray[$key] = $value;
						}
						if ($value === null) {
							$row[$key] = 'NULL';
						}
						$i++;
					}
					$rowarray[] = $subrowarray;
					if (ENV_MODE & ENV_WEB) {
						$temp .= '<tr><td' . $textstyle . '>' . implode('</td><td' . $textstyle . '>', $row) . '</td></tr>';
					}
				}
				if ((ENV_MODE & ENV_WEB) && ($temp === '')) {
					if (preg_match('/^SELECT/i', $query['query']) > 0) {
						$return .= '<tr><td colspan="12"' . $errstyle . '>Erronymous query.' . '</td></tr>';
					}
					else {
						$return .= '<tr><td colspan="12"' . $errstyle . '>Unknown query.' . '</td></tr>';
					}
				}
				elseif (ENV_MODE & ENV_WEB) {
					$return .= $temp;
				}
				elseif (!empty($rowarray)) {
					$return .= '+';
					foreach ($charcount as $value) {
						$return .= str_repeat('-', $value + 2) . '+';
					}
					$return .= "\n|";
					foreach ($fieldsarray as $value) {
						$return .= ' ' . str_pad($value, $charcount[$value], ' ', STR_PAD_BOTH) . ' |';
					}
					foreach ($rowarray as $row) {
						$return .= "\n+";
						foreach ($charcount as $value) {
							$return .= str_repeat('-', $value + 2) . '+';
						}
						$return .= "\n|";
						foreach ($row as $key => $value) {
							$return .= ' ' . str_pad($value, $charcount[$key], ' ', STR_PAD_RIGHT) . ' |';
						}
					}
					$return .= "\n+";
					foreach ($charcount as $value) {
						$return .= str_repeat('-', $value + 2) . '+';
					}
					$return .= "\n";
				}
				else {
					if (preg_match('/^SELECT/i', $query['query']) > 0) {
						$return .= colorize('Erronymous query.', 'error', $background) . "\n";
					}
					else {
						$return .= colorize('Unknown query.', 'error', $background) . "\n";
					}
				}
			}
			elseif ($query['error'] !== false) {
				if (ENV_MODE & ENV_WEB) {
					$return .= '<tr><td colspan="12"' . $errstyle . '>Error (' . $query['error']['errno'] . '): ' . $query['error']['error'] . '</td></tr>';
				}
				else {
					$return .= colorize('Error (' . $query['error']['errno'] . '): ' . $query['error']['error'], 'error', $background) . "\n";
				}
			}
			elseif (ENV_MODE & ENV_WEB) {
				$return .= $temp;
			}
			if (ENV_MODE & ENV_WEB) {
				$return .= '</table><br>';
			}
			else {
				$return .= "\n";
			}
		}
		if (count(array_unique($duplicates)) < count($duplicates)) {
			$return .= colorize('You have duplicate queries!', 'error') . '<br>';
		}
		$return .= colorize('Total sql time: ' . colorize((string) $sqltime, 'range:{"type": "alert", "max":0.3, "value":' . $sqltime . '}'), 'black') . (ENV_MODE & ENV_WEB ? '<br>' : "\n");
		$return .= colorize('Total sql queries: ' . $sqlnum, 'black') . (ENV_MODE & ENV_CLI ? "\n" : '');
		return $return;
	}

	/**
	 * Method added to
	 *
	 * @return array
	 */
	public function explainJson (): array
	{
		$return = [
			'time' => 0,
			'num' => 0,
			'duplicates' => false,
			'queries' => [],
		];
		$sqlnum = 0;
		$duplicates = [];
		foreach ($this->queryCache as $query) {
			$duplicates[] = $query['query'];
			$return['time'] += $query['time'];
			$return['num']++;

			if (($query['error'] === false) && (preg_match('/^SELECT/i', $query['query']) > 0)) {
				$fieldsarray = [];
				$res = parent::query('EXPLAIN ' . $query['query']);
				$fields = $res->fetch_fields();
				foreach ($fields as $field) {
					$fieldsarray[] = $field->name;
				}
			}

			$return['queries'][] = $query;
		}
		if (count(array_unique($duplicates)) < count($duplicates)) {
			$return['duplicates'] = true;
		}

		return $return;
	}
}
