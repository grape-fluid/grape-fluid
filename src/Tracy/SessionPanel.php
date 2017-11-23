<?php

namespace Grapesc\GrapeFluid\Tracy;

use Nette;
use Tracy;


/**
 * @author Kulíšek Patrik <kulisek@grapesc.cz>
 */
class SessionPanel extends Nette\Object implements Tracy\IBarPanel
{

	/** @var Nette\Http\Session */
	protected $session;


	public function __construct(Nette\Http\Session $session)
	{
		$this->session = $session;
	}


	public function getTab()
	{
		return 	"<span title='Aktuální sessions'>" .
				"<img src=\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAo
				LQ9TAAAA21BMVEVeMgNgMAJlMwJoNQJsNgJ4PwKMTgGOTgCTUwCVVQD///9tNwJbLgNRKQNuNw
				JsNgKIRQGPTwBuNwJuNwJfMAJoNQK6XwRlMwKVVQCuWQNhMQKgUQJrNgRdLwJXLANNJwOSSgGy
				jWqHRAFfOxlsLABzMwB9PQCISACVVQCvkXTYvqTKsJaAZk2Rd16iiG+jiXCli3Ktk3qvlXyxl3
				6ymH+0moG1m4K3nYS6oIfJr5bMspnbwajcwandqmbdw6rewqvew6vhr3zhxK7jxbDly7LouYbs
				0rnvw5D1y5igBQNaAAAALHRSTlMAAAAAAAAAAAAAABASQV1jY2Z3en+JkZiZnaSrrq+2urrDxs
				vMzMzMzOr6+7tXzd4AAACvSURBVBgZBcHLjsIwDEDR69hJ1VZo1FmyYM///xCsR7wkBG2a2HOO
				TNRBTYBofSsYYL/jAjy+9w0SRJmOs+p8nEpAgkiHura21kMKMAhdBSdYNcBg1t6oFLrOjoGU2A
				g2oggYDOXHVAhvffhgMC6x7wAs7w8G8hVJQoSHgIHfAQBwSFDP+TRer+MpnysYaAiMgISCQXL5
				e8IT8QQJcpPLC14XaRk0s9fb7u5B3R6dfxPcUkFVVpaqAAAAAElFTkSuQmCC\" />" .
				" Sessions (" . $this->session->getIterator()->count() . ")" .
				"</span>";
	}


	function getPanel() {
		$html = 	"<h1>Aktuální sessions</h1>
					<style>
						#tracy-debug .tracy-sessionPanel h2 {
							font: 11pt/1.5 sans-serif;
							margin: 0;
							padding: 2px 8px;
							background: #3484d2;
							color: white;
							display: block;
						}
						#tracy-debug .tracy-inner-overflow {
							max-height: 100% !important;
						}
					</style>
					<div class='tracy-inner tracy-inner-overflow tracy-sessionPanel'>";

		foreach ($this->session->getIterator() as $sectionName) {
			if ($sectionName == "Nette.Http.UserStorage/") {
				continue;
			} else {
				$section = $this->session->getSection($sectionName);
				if ($section->getIterator()->count() != 0) {
					$html .= "<h2 class='tracy-toggle tracy-collapsed'>$sectionName</h2>";
					$html .= "<div class='tracy-collapsed'>";
					$html .= Tracy\Debugger::dump($this->session->getSection($sectionName)->getIterator(), true);
					$html .= "</div>";
				}
			}
		}

		$html .= "</div>";
		return $html;
	}

}