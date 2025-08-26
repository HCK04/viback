<?php
namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        // Return all roles with id and name
        return Role::select('id', 'name')->get();
    }
}