<div class="modal fade" role="dialog" id="confirm-qq-registration">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">
          <i class="fab fa-qq"></i> QQ Registration
        </h4>
        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Please join the QQ Group with the information displayed below.</p>
        <form method="post" id="qq-registration-form" class="form-horizontal">
          {{ csrf_field() }}
          <div class="form-group">
            <label class="col-sm-3 control-label" for="qq-number">QQ Number</label>
            <div class="col-sm-9">
              <input type="text" value="{{ $registration_nickname }}" readonly="readonly" id="qq-number" class="form-control input-sm" />
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary pull-left" type="button" data-dismiss="modal">Close</button>
        <button class="btn btn-success" type="submit" form="qq-registration-form">
          <i class="fas fa-check"></i> Confirm
        </button>
      </div>
    </div>
  </div>
</div>