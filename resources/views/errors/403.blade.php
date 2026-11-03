@extends('errors::layout')

@section('code', '403')
@section('title', 'You cannot open this page')
@section('message', $exception->getMessage() ?: 'You do not have permission to see this page. If you think you should, ask your club secretary or an administrator.')
