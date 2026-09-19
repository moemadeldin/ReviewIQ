@extends('errors.layout')

@section('title', __('Unauthorized'))
@section('code', '401')
@section('message', __('Sorry, you need to sign in to access this page.'))