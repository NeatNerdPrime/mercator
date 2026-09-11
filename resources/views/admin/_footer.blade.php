<div class="card-footer">
    {{ trans('global.created_at') }} {{ $model->created_at ? $model->created_at->format(trans('global.timestamp')) : '' }} |
    {{ trans('global.updated_at') }} {{ $model->updated_at ? $model->updated_at->format(trans('global.timestamp')) : '' }}
</div>
