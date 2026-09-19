@extends('errors.layout')

@section('title', __('Unprocessable Content'))
@section('code', '422')
@section('message', __('Sorry, your request is missing required fields. Go back and check your input.'))