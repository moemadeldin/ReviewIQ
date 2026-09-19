@extends('errors.layout')

@section('title', __('Method Not Allowed'))
@section('code', '405')
@section('message', __('Sorry, this page does not support what you\'re trying to do. Check the address and try again.'))