<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DTO\ClientData;
use App\DTO\ServerData;
use App\DTO\DatabaseData;

class NewController extends Controller
{
    public function serverInfo()
    {
        $data = new ServerData(
            phpversion()
        );
        
        return response()->json($data);
    }

    public function clientInfo(Request $request)
    {
        $data = new ClientData(
            $request->ip(),
            $request->userAgent()
        );
        
        return response()->json($data);
    }

    public function databaseInfo()
    {
        $connection = config('database.default');
        $data = new DatabaseData(
            config("database.connections.{$connection}.driver"),
            config("database.connections.{$connection}.database")
        );
        
        return response()->json($data);
    }
}