<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Validation;

use BoldMinded\DataGrab\Dependency\Illuminate\Validation\Validator;
interface ValidatorAwareRule
{
    /**
     * Set the current validator.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return $this
     */
    public function setValidator(Validator $validator);
}
