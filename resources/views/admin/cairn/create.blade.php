{{--
    TEMPORAIRE — page de vérification manuelle de la Phase 3 (canevas + rendu client-side).
    À remplacer par la vue create/edit du contrôleur resourceful (prompt "sauvegarde").
--}}
@extends('layouts.admin')

@section('title')
    {{ trans('cruds.cairn.title') }}
@endsection

@section('content')
    @include('admin.cairn.applications')
@endsection

@section('scripts')
    @parent
    @vite(['resources/js/cairn.js'])
@endsection
