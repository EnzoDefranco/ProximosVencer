<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #000000;
            padding: 5px;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .bg-red {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .bg-yellow {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .bg-green {
            background-color: #dcfce7;
            color: #15803d;

            < !DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>table {
                border-collapse: collapse;
                width: 100%;
                font-family: Arial, sans-serif;
            }

            th,
            td {
                border: 1px solid #e5e7eb;
                padding: 8px;
                vertical-align: middle;
                font-size: 12px;
            }

            /* Header styling */
            th {
                background-color: #012b67;
                color: #ffffff;
                font-weight: bold;
                text-align: center;
            }

            .text-right {
                text-align: right;
            }

            .text-center {
                text-align: center;

                < !DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>table {
                    border-collapse: collapse;
                    width: 100%;
                    font-family: 'Calibri', 'Arial', sans-serif;
                }

                th,
                td {
                    border: 1px solid #e5e7eb;
                    padding: 10px;
                    vertical-align: middle;
                    font-size: 11pt;
                }

                /* Header styling */
                th {
                    background-color: #012b67;
                    color: #ffffff;
                    font-weight: bold;
                    text-align: center;
                    height: 40px;
                }

                .text-right {
                    text-align: right;
                }

                .text-center {
                    text-align: center;
                }

                .font-bold {
                    font-weight: bold;
                }

                /* Status colors */
                .bg-red {
                    background-color: #fee2e2;
                    color: #b91c1c;
                }

                .bg-yellow {
                    background-color: #fef9c3;
                    color: #854d0e;
                }

                .bg-green {
                    background-color: #dcfce7;
                    color: #15803d;
                }

                .bg-gray {
                    background-color: #f3f4f6;
                    color: #374151;
                }
    </style>
</head>

<body>
    <table>
        <thead>
            <tr>
                <th colspan="7"
                    style="height: 40px; font-size: 16pt; background-color: #012b67; color: #ffffff; text-align: center; vertical-align: middle;">
                    Próximos Vencimientos — {{ \Carbon\Carbon::parse($fechaHoy)->format('d/m/Y') }}
                </th>
            </tr>
            <tr>
                <th style="width: 60px; background-color: #f3f4f6; color: #1f2937;">OK</th>
                <th style="width: 130px; background-color: #f3f4f6; color: #1f2937;">Artículo</th>
                <th style="width: 110px; background-color: #f3f4f6; color: #1f2937;">Creado</th>
                <th style="width: 500px; background-color: #f3f4f6; color: #1f2937;">Descripción</th>
                <th style="width: 120px; background-color: #f3f4f6; color: #1f2937;">Vence</th>
                <th style="width: 100px; background-color: #f3f4f6; color: #1f2937;">Unidades</th>
                <th style="width: 100px; background-color: #f3f4f6; color: #1f2937;">Días</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $row)
                @php
                    $d = $row->diasRestantes;
                    $class = '';
                    if (!is_null($d)) {
                        if ($d <= 7)
                            $class = 'bg-red';
                        elseif ($d <= 30)
                            $class = 'bg-yellow';
                        else
                            $class = 'bg-green';
                    } else {
                        $class = 'bg-gray';
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $row->checked ? 'SI' : 'NO' }}</td>
                    <td class="text-center">{{ $row->ArticuloCodigo }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y') }}</td>
                    <td>{{ $row->ArticuloDescripcion }}</td>
                    <td class="text-center {{ $class }}">
                        {{ \Carbon\Carbon::parse($row->fechaVencimiento)->format('d/m/Y') }}</td>
                    <td class="text-right">{{ $row->Unidades }}</td>
                    <td class="text-center {{ $class }}">{{ $d ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>