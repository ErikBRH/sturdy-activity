<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use Generator;
use stdClass;

final class Diagrams
{
	private $colors;

	/**
	 * Set unit
	 *
	 * @param Unit $unit
	 * @return self
	 */
	public function setUnit(Unit $unit): self
	{
		$this->unit = $unit;
		return $this;
	}

	/**
	 * Get unit
	 *
	 * @return Unit
	 */
	public function getUnit(): Unit
	{
		return $this->unit;
	}

	/**
	 * Set class color
	 *
	 * @param string $class color
	 * @return self
	 */
	public function setClassColor(string $class, string $color): self
	{
		$this->colors[$class] = $color;

		return $this;
	}

	/**
	 * Get class color
	 *
	 * @return string
	 */
	public function getClassColor(string $class): ?string
	{
		return $this->colors[$class]??null;
	}

	/**
	 * Generate class colors for classes.
	 */
	public function generateClassColors(): void
	{
		$this->colors = array_merge(array_flip($this->unit->getClasses()), $this->colors??[]);
		foreach ($this->colors as &$color) {
			if (is_string($color)) continue;
			$i = 0;
			do {
				$r = dechex(random_int(7, 15)*16);
				if (strlen($r) == 1) $r = "0$r";
				$g = dechex(random_int(7, 15)*16);
				if (strlen($g) == 1) $g = "0$g";
				$b = dechex(random_int(7, 15)*16);
				if (strlen($b) == 1) $b = "0$b";
				$c = "#$r$g$b";
				if ($i++ > 20) break; // don't get stuck here
			} while (!in_array($c, $this->colors, true));
			$color = $c;
		}
	}

	/**
	 * Generate the UML for the activity diagrams.
	 */
	public function generate(): Generator
	{
		foreach ($this->unit->getActions() as $dims => $actions) {
			$compiler = $this->compile($actions, "start");
			foreach ($compiler as $action); // simply run the compiler
			$branch = $compiler->getReturn();

			$filename = trim("activity $dims");
			yield "\0$filename.uml";
			yield "@startuml\n";
			$dimensions = $this->unit->getDimensions();
			if (count($dimensions)) {
				yield "floating note left\n";
				foreach (array_combine($dimensions, explode(" ", $dims)) as $dimension => $value) {
					yield "\t$dimension: $value\n";
				}
				yield "end note\n";
			}
			yield from $this->writeDiagram($branch);
			yield "@enduml\n";
		}
	}

	/**
	 * Compile diagram
	 *
	 * @param $actions  actions to write
	 * @param $action   the next action
	 * @return the diagram
	 */
	private function compile(array $actions, string $action): Generator
	{
		$branch = [];
		$pastActions = [];
		$lastAction = $action;
		while (array_key_exists($action, $actions)) {
			$next = $actions[$action];
			if (empty($next)) {
				$branch[] = $action;
				end($branch); $pastActions[$action] = key($branch);
				$branch[] = "stop";
				try {
					yield "stop";
				} catch (\Exception $e) {} // suppress exception
				break;
			} elseif (is_string($next)) {
				$branch[] = $action;
				end($branch); $pastActions[$action] = key($branch);
				$action = $next;
			} elseif (is_array($next)) {
				switch (count($next)) {
					case 2:
						// check if it is a loop
						foreach ($next as $retval => $altaction) {
							if (isset($pastActions[$altaction])) {
								unset($next[$retval]);
								$next = reset($next);
								$line = new stdClass;
								$compiler = $this->compile($actions, $next);
								foreach ($compiler as $a);
								$tail = $compiler->getReturn();
								array_unshift($tail, $line);
								$line->type = "repeat";
								$line->isval = $retval;
								$line->action = $action;
								$line->branch = array_splice($branch, $pastActions[$altaction], count($branch), $tail);
								return $branch;
							}
						}
					default:
						$branch[] = $action;
						end($branch); $pastActions[$action] = key($branch);
						// create compilers
						$compilers = [];
						foreach ($next as $retval => $altaction) {
							$compilers[$retval] = $this->compile($actions, $altaction);
						}
						// run compilers in parallel until an action is found that is executed by all branches
						$alts = [];
						$branches = [];
						$method = 'rewind';
						while (1) {
							foreach ($compilers as $retval => $compiler) {
								if (!$compiler->valid()) continue;
								$compiler->$method();
								if (!$compiler->valid()) { // compiler is done
									$branches[$retval] = $compiler->getReturn();
									continue;
								}
								$action = $compiler->current();
								$alts[$action][$retval] = $retval;
								if (count($alts[$action]) === count($next)) {
									unset($alts);
									// end of alternatives found
									foreach ($compilers as $retval => $compiler) {
										try {
											$compiler->throw(new \Exception("interrupt"));
										} catch (\Exception $e) {}
										while ($compiler->valid()) $compiler->next();
										$branches[$retval] = $compiler->getReturn();
									}
									foreach ($branches as $retval => $altbranch) {
										$ix = array_search($action, $altbranch);
										if ($ix !== false) {
											$branches[$retval] = array_slice($altbranch, 0, $ix);
										}
									}
									break 2;
								}
							}
							$method = 'next';
						}
						$branch[] = $branches;
				}
			} else {
				throw new \Exception("Invalid diagram");
			}
			// yield current action for parallel compiling
			try {
				yield $action;
			} catch (\Exception $e) {
				break;
			}
			if ($lastAction === $action) {
				throw new \Exception("next failed");
			}
			$lastAction = $action;
		}
		return $branch;
	}

	/**
	 * Write branch
	 *
	 * @param $branch  branch to write
	 * @param $indent  indent to prefix lines with
	 * @return the formatted action
	 */
	private function writeDiagram(array $branch, string $indent = ""): Generator
	{
		foreach ($branch as $line) {
			if (is_string($line)) {
				$type = "action";
			} elseif (is_array($line)) {
				$type = "if";
			} else {
				$type = $line->type;
			}
			switch ($type) {
				case "action":
					yield $indent.$this->formatAction($line);
					break;
				case "if":
					$i = 0;
					$l = count($line) - 1;
					foreach ($line as $retval => $branch) {
						if ($i === 0) {
							yield $indent."if (r) then ($retval)\n";
						} elseif ($i === $l) {
							yield $indent."else ($retval)\n";
						} else {
							yield $indent."elseif (r) then ($retval)\n";
						}
						yield from $this->writeDiagram($branch, "$indent\t");
						++$i;
					}
					yield $indent."endif\n";
					break;
				case "repeat":
					yield $indent."repeat\n";
					yield from $this->writeDiagram($line->branch, "$indent\t");
					yield $indent."\t".$this->formatAction($line->action);
					yield $indent."repeat while (r = {$line->isval})\n";
					break;
			}
		}
	}

	/**
	 * Format a action.
	 *
	 * @param $action  the action to format
	 * @return the formatted line
	 */
	private function formatAction(string $action): string
	{
		$p = strpos($action, "::");
		if ($p !== false) {
			$className = substr($action, 0, $p);
			$methodName = substr($action, $p+2);
			return $this->colors[$className].":$methodName|\n";
		} else {
			return ":$action;\n";
		}
	}
}