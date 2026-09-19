@extends('errors.layout')

@section('title', __('Bad Request'))
@section('code', '400')
@section('message', __('Sorry, your request was malformed and could not be processed.'))