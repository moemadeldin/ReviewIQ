@extends('errors.layout')

@section('title', __('Gateway Timeout'))
@section('code', '504')
@section('message', __('Sorry, our servers took too long to respond. Please try again.'))