<?php

namespace rdx\combelldns;

use rdx\jsdom\Node;

class JsDomNode extends Node {

	public function getFormValue( $name ) {
		$element = $this->query('input[name="' . $name . '"]');
		return $element ? $element['value'] : null;
	}

	public function getFormValues() {
		$elements = $this->queryAll('input');

		$values = [];
		foreach ( $elements as $element ) {
			$values[ $element['name'] ] = $element['value'];
		}

		return $values;
	}

}
