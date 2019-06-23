<?php

namespace Graph\Resource;

use ArrayAccess;

interface ResourceInterface extends ArrayAccess
{
    public function getName();
}