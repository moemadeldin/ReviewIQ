@extends('errors.layout')

@section('title', __('Payload Too Large'))
@section('code', '413')
@section('message', __('Sorry, the file you are trying to upload is too large.'))