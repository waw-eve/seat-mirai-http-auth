@extends('seat-connector::identities.list')

@section('identity-modal')
@include('seat-connector-mirai::registrations.includes.modal')
@endsection

@push('javascript')
<script>
  $(document).ready(function() {
    $('#confirm-qq-registration').modal('toggle');
  });
</script>
@endpush