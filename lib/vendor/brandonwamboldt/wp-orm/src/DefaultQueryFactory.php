<?php

namespace No3x\WPML\ORM;


class DefaultQueryFactory implements QueryFactory {
    public function buildQuery($modelClass) {
        return new Query($modelClass);
    }
}
