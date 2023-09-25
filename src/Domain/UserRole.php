<?php

namespace App\Domain;

enum UserRole
{
    case Administrator;
    case Editor;
    case User;
}
