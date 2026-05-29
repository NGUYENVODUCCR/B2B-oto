@extends('layouts.app')

@section('content')
  @php
    $slug = get_post_field('post_name', get_post());
    $isSupportWorkspaceRoute = $slug === 'support'
      && (string) get_query_var('b2b_support_workspace') === '1';

    if ($isSupportWorkspaceRoute) {
      $slug = 'support-workspace';
    }

    $pageViews = [
      'login' => 'pages.login',
      'register' => 'pages.register',
      'verify-otp' => 'pages.verify-otp',
      'forgot-password' => 'pages.forgot-password',
      'reset-password' => 'pages.reset-password',
      'dashboard' => 'pages.dashboard',
      'chat' => 'pages.chat',
      'messages' => 'pages.chat',
      'wallet' => 'pages.wallet',
      'payment' => 'pages.payment',
      'revenue' => 'pages.revenue',
      'orders' => 'pages.orders',
      'seller-request' => 'pages.seller-request',
      'product-create' => 'pages.product-create',
      'product-list' => 'pages.product-list',
      'rfq-create' => 'pages.rfq-create',
      'rfq-list' => 'pages.rfq-list',
      'quotation-list' => 'pages.quotation-list',
      'admin' => 'pages.admin',
      'seller' => 'pages.seller',
      'support' => 'pages.support',
      'support-workspace' => 'pages.support-workspace',
    ];
  @endphp

  @if(isset($pageViews[$slug]) && view()->exists($pageViews[$slug]))
    @include($pageViews[$slug])
  @else
    <h1>{{ get_the_title() }}</h1>
    {!! the_content() !!}
  @endif
@endsection
