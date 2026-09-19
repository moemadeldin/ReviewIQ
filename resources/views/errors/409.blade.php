@extends('errors.layout')

@section('title', __('Conflict'))
@section('code', '409')
@section('message', __('Sorry, your request conflicted with the current state of this workspace. Refresh and try again.'))