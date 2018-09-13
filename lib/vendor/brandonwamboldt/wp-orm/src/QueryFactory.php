<?php

namespace No3x\WPML\ORM;


interface QueryFactory {
    public function buildQuery($modelClass);
}
