<?php

namespace Pipeandpiper\API\Enums;

/**
 * Allowed CRUD methods for an API endpoint
 */
enum AllowedMethodEnum: string
{
    case CREATE = 'POST';
    case READ = 'GET';
    case UPDATE = 'PUT';
    case DELETE = 'DELETE';
    case PUBLIC = 'PUBLIC';
}
