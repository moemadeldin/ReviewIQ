@extends('errors.layout')

@section('title', __('Too Many Requests'))
@section('code', '429')
@section('message', __('Sorry, you are making too many requests. Please wait a moment and try again.'))