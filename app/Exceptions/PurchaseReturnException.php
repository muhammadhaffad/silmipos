<?php
namespace App\Exceptions;

use Exception;
use Throwable;

class PurchaseReturnException extends Exception {
    public function __construct($message, $code = 0, Throwable $previous = null) {
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }
}
?>