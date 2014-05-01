<?php

namespace FML\Controls\Quads;

use FML\Controls\Quad;

/**
 * Quad Class for 'TitleLogos' Style
 *
 * @author steeffeen
 */
class Quad_TitleLogos extends Quad {
	/*
	 * Constants
	 */
	const STYLE = 'TitleLogos';
	const SUBSTYLE_Author = 'Author';
	const SUBSTYLE_Collection = 'Collection';
	const SUBSTYLE_Icon = 'Icon';
	const SUBSTYLE_Title = 'Title';

	/**
	 * Create a new Quad_TitleLogos Control
	 *
	 * @param string $id (optional) Control Id
	 * @return \FML\Controls\Quads\Quad_TitleLogos
	 */
	public static function create($id = null) {
		$quadTitleLogos = new Quad_TitleLogos($id);
		return $quadTitleLogos;
	}

	/**
	 * Construct a new Quad_TitleLogos Control
	 *
	 * @param string $id (optional) Control Id
	 */
	public function __construct($id = null) {
		parent::__construct($id);
		$this->setStyle(self::STYLE);
	}
}
