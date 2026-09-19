@extends('errors.layout')

@section('title', __('Server Error'))
@section('code', '500')
@section('message', __('Whoops, something went wrong on our end. Please try again later.'))