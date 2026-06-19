
export const initChartThree = () => {
    const chartElement = document.querySelector('#chartThree');

    if (chartElement) {
        const labels = JSON.parse(chartElement.dataset.chartLabels || '[]');
        const revenue = JSON.parse(chartElement.dataset.chartRevenue || '[]');
        const profit = JSON.parse(chartElement.dataset.chartProfit || '[]');
        const chartThreeOptions = {
            series: [{
                name: "Omzet",
                data: revenue,
            },
            {
                name: "Laba Kotor",
                data: profit,
            },
            ],
            legend: {
                show: true,
                position: "top",
                horizontalAlign: "left",
            },
            colors: ["#465FFF", "#9CB9FF"],
            chart: {
                fontFamily: "Public Sans, sans-serif",
                height: 220,
                type: "area",
                toolbar: {
                    show: false,
                },
            },
            fill: {
                gradient: {
                    enabled: true,
                    opacityFrom: 0.55,
                    opacityTo: 0,
                },
            },
            stroke: {
                curve: "straight",
                width: ["2", "2"],
            },
            markers: {
                size: 0,
            },
            labels: {
                show: false,
                position: "top",
            },
            grid: {
                xaxis: {
                    lines: {
                        show: false,
                    },
                },
                yaxis: {
                    lines: {
                        show: true,
                    },
                },
            },
            dataLabels: {
                enabled: false,
            },
            tooltip: {
                x: {
                    show: false,
                },
                y: {
                    formatter: function (val) {
                        return new Intl.NumberFormat('id-ID', {
                            style: 'currency',
                            currency: 'IDR',
                            maximumFractionDigits: 0,
                        }).format(Number(val || 0)).replace(/\s/g, '');
                    },
                },
            },
            xaxis: {
                type: "category",
                categories: labels,
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
                tooltip: false,
            },
            yaxis: {
                title: {
                    style: {
                        fontSize: "0px",
                    },
                },
            },
        };

        const chart = new ApexCharts(chartElement, chartThreeOptions);
        chart.render();
        return chart;
    }
}

export default initChartThree;
