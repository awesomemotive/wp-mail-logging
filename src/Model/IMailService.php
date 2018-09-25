<?php
/**
 * Created by IntelliJ IDEA.
 * User: noex_
 * Date: 24.09.2018
 * Time: 17:37
 */

namespace No3x\WPML\Model;

interface IMailService {
    /**
     * Find a specific model by it's unique ID.
     *
     * @param  integer $id
     * @return false|WPML_Mail
     */
    public function find_one($id);
}
