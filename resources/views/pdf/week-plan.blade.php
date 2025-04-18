<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Kế hoạch học tuần #{{ $week->id }}</title>
  <style>
    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 14px;
      line-height: 1.6;
    }

    h1 {
      font-size: 20px;
      margin-bottom: 10px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    th,
    td {
      border: 1px solid #333;
      padding: 6px 8px;
      text-align: left;
    }

    th {
      background: #eee;
    }
  </style>
</head>

<body>
  <h1>Kế hoạch học tuần bắt đầu: {{ $week->start_date->format('d/m/Y') }}</h1>
  <p><strong>Tóm tắt:</strong> {{ $week->summary }}</p>
  <p><strong>Ghi chú:</strong> {{ $week->notes }}</p>

  <table>
    <thead>
      <tr>
        <th>Thứ</th>
        <th>Nội dung</th>
        <th>Thời lượng</th>
        <th>Loại</th>
        <th>Nguồn</th>
        <th>Focus</th>
        <th>Hoàn thành</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($week->tasks as $task)
        <tr>
          <td>{{ $task->day }}</td>
          <td>{{ $task->task }}</td>
          <td>{{ $task->duration }}</td>
          <td>{{ $task->type }}</td>
          <td>{{ $task->resource }}</td>
          <td>{{ $task->focus }}</td>
          <td>{{ $task->is_done ? '✅' : '❌' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>

</html>
