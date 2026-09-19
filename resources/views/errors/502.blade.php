@extends('errors.layout')

@section('title', __('Bad Gateway'))
@section('code', '502')
@section('message', __('Sorry, our upstream service had a hiccup. Please try again in a moment.'))