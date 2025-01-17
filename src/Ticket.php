<?php

namespace BadChoice\Handesk;

use GuzzleHttp\Client;

class Ticket extends Handesk {
    const STATUS_NEW                = 1;
    const STATUS_OPEN               = 2;
    const STATUS_PENDING            = 3;
    const STATUS_SOLVED             = 4;
    const STATUS_CLOSED             = 5;

    const PRIORITY_LOW              = 1;
    const PRIORITY_NORMAL           = 2;
    const PRIORITY_HIGH             = 3;

    public    $comments = [];
    protected $objects = ["requester"];

    public function __construct(array $attributed = []) {
        parent::__construct($attributed);
        if( ! isset($attributed["comments"])) return;
        $this->comments = array_map(function($comment){
            return new TicketComment($comment);
        },$attributed["comments"]);
        $this->comments[] = new TicketComment(["body" => $this->body, "created_at" => $this->attributes["created_at"], "author" => $this->requester]);
    }

    public function create($requester, $title, $body, $tags, $team_id = null){
        $response = (new Client())->request("POST", static::$url . "/tickets", [
            "headers" => ["token" => static::$apiToken],
            "form_params" => [
                "requester" => $requester,
                "title"     => $title,
                "body"      => $body,
                "tags"      => $tags,
                "team_id"   => $team_id,
            ]
        ]);
        return json_decode($response->getBody())->data->id;
    }

    public function get($requester, $status = 'open'){
        try {
            $response = Http::withHeaders([
                "token" => static::$apiToken
            ])->get(static::$url . "/tickets?requester={$requester}&status={$status}");
            return array_map(function ($attributes) {
                return new Ticket($attributes);
            }, $response["data"]);
        }catch(\Exception $e){
            return [];
        }
    }

    public function find($id){
        $response = (new Client())->request("GET", static::$url . "/tickets/{$id}", ["headers" => ["token" => static::$apiToken]] );
        return new Ticket( json_decode($response->getBody(),true)["data"] );
    }

    public function addComment($comment, $solved = false){
        $response = (new Client())->request("POST", static::$url . "/tickets/{$this->id}/comments",[
            "headers" => ["token" => static::$apiToken],
            "form_params" => [
                "body"          => $comment,
                "new_status"    => $solved ? static::STATUS_SOLVED : null,
            ]
        ]);
    }

    public function statusName(){
        switch ($this->status){
            case static::STATUS_NEW                 : return "new";
            case static::STATUS_OPEN                : return "open";
            case static::STATUS_PENDING             : return "pending";
            case static::STATUS_SOLVED              : return "solved";
            case static::STATUS_CLOSED              : return "closed";
        }
    }

    public function findEncryptedId($id){
        return $this->find($this->getDecryptedId($id));
    }

    public function getDecryptedId($id) {
        return decrypt($id);
    }

    public function getEncryptedId() {
        return encrypt($this->id);
    }

}
