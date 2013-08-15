<?php

namespace Less\Node;

class Dimension{

    public function __construct($value, $unit = false){
        $this->value = floatval($value);

		if( $unit && ($unit instanceof \Less\Node\Unit) ){
			$this->unit = $unit;
		}elseif( $unit ){
			$this->unit = new \Less\Node\Unit( array($unit) );
		}else{
			$this->unit = new \Less\Node\Unit( );
		}
    }

    public function compile($env = null) {
        return $this;
    }

    public function toColor() {
        return new \Less\Node\Color(array($this->value, $this->value, $this->value));
    }

    public function toCSS(){
        return $this->unit->isEmpty() ? $this->value :
			$this->value . $this->unit->toCSS();
    }

    public function __toString(){
        return $this->toCSS();
    }

    // In an operation between two Dimensions,
    // we default to the first Dimension's unit,
    // so `1px + 2em` will yield `3px`.
    public function operate($op, $other){

		$value = \Less\Environment::operate($op, $this->value, $other->value);
		$unit = clone $this->unit;

		if( $op === '+' || $op === '-' ){

			if( !count($unit->numerator) && !count($unit->denominator) ){
				$unit->numerator = $other->unit->numerator;
				$unit->denominator = $other->unit->denominator;
			}elseif( !count($other->unit->numerator) && !count($other->unit->denominator) ){
				// do nothing
			}else{
				$other = $other->convertTo( $this->unit->usedUnits());

				if( $other->unit->toCSS() != $unit->toCSS() ){
					throw new \Less\Exception\CompilerException("Incompatible units '".$unit->toCSS() . "' and ".$other->unit->toCSS()+"'.");
				}

				$value = \Less\Environment::operate($op, $this->value, $other->value);
			}
		}elseif( $op === '*' ){
			$unit->numerator = array_merge($unit->numerator, $other->unit->numerator);
			$unit->denominator = array_merge($unit->denominator, $other->unit->denominator);
			sort($unit->numerator);
			sort($unit->denominator);
			$unit->cancel();
		}elseif( $op === '/' ){
			$unit->numerator = array_merge($unit->numerator, $other->unit->denominator);
			$unit->denominator = array_merge($unit->denominator, $other->unit->numerator);
			sort($unit->numerator);
			sort($unit->denominator);
			$unit->cancel();
		}
		return new \Less\Node\Dimension( $value, $unit);
    }

	public function compare($other) {
		if ($other instanceof Dimension) {

			$a = $this->unify()->value;
			$b = $other->unify()->value;

			if ($b > $a) {
				return -1;
			} elseif ($b < $a) {
				return 1;
			} else {
				if ($other->unit && $this->unit !== $other->unit) {
					return -1;
				}
				return 0;
			}
		} else {
			return -1;
		}
	}

	function unify() {
		return $this->convertTo(array('length'=> 'm', 'duration'=> 's' ));
	}

    function convertTo($conversions) {
		$value = $this->value;
		$unit = clone $this->unit;

		foreach($conversions as $groupName => $targetUnit){
			$group = \Less\Node\UnitConversions::$$groupName;

			//numerator
			for($i=0; $i < count($unit->numerator); $i++ ){
				$atomicUnit = $this->numerator[$i];
				if( !isset($group[$atomicUnit]) ){
					continue;
				}

				$value = $value * ($group[$atomicUnit] / $group[$targetUnit]);

				$unit->numerator[$i] = $targetUnit;
			}

			//denominator
			for($i=0; $i < count($unit->denominator); $i++ ){
				$atomicUnit = $this->denominator[$i];
				if( !isset($group[$atomicUnit]) ){
					continue;
				}

				$value = $value / ($group[$atomicUnit] / $group[$targetUnit]);

				$unit->denominator[$i] = $targetUnit;
			}
		}

		$unit->cancel();

		return new \Less\Node\Dimension( $value, $unit);
    }
}
