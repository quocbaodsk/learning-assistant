<template>
    <div class="p-4 bg-white shadow sm:p-8 sm:rounded-lg">
        <header>
            <h2 class="text-lg font-medium text-gray-900">Lịch sử học tập</h2>
            <p class="mt-1 text-sm text-gray-600">
                Xem lại các tuần học tập trước đây của bạn.
            </p>
        </header>

        <div class="mt-6">
            <div v-if="loading" class="flex items-center justify-center p-6">
                <div class="inline-block w-8 h-8 border-4 border-indigo-500 rounded-full animate-spin border-t-transparent"></div>
                <p class="ml-3 text-sm text-gray-600">Đang tải lịch sử...</p>
            </div>

            <div v-else-if="error" class="p-4 mt-4 rounded-md bg-red-50">
                <div class="flex">
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Có lỗi xảy ra</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>{{ error }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="!weeks.length" class="py-6 text-center">
                <p class="text-gray-500">Chưa có lịch sử học tập.</p>
            </div>

            <div v-else class="mt-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                    Tuần
                                </th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                    Ngày bắt đầu
                                </th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                    Tiến độ
                                </th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                    Trạng thái
                                </th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-right text-gray-500 uppercase">
                                    Thao tác
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(week, index) in weeks" :key="week.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-900">Tuần {{ index + 1 }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-500">{{ formatDate(week.start_date) }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div class="bg-blue-600 h-2.5 rounded-full" :style="{ width: `${Math.round((week.done_tasks / week.total_tasks) * 100)}%` }"></div>
                                        </div>
                                        <span class="ml-2 text-xs text-gray-500">
                                            {{ week.done_tasks }}/{{ week.total_tasks }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 text-xs font-semibold leading-5 rounded-full" :class="{
                                        'bg-green-100 text-green-800': week.is_active,
                                        'bg-gray-100 text-gray-800': !week.is_active
                                    }">
                                        {{ week.is_active ? 'Đang học' : 'Đã hoàn thành' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-right whitespace-nowrap">
                                    <button @click="viewWeekDetails(week.id)" class="mr-2 text-indigo-600 hover:text-indigo-900">
                                        Xem
                                    </button>
                                    <a href="#" class="text-green-600 hover:text-green-900" @click.prevent="downloadPdf(week.id)">
                                        PDF
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal xem chi tiết tuần -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500 bg-opacity-75">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Chi tiết tuần học</h3>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="modalLoading" class="flex items-center justify-center p-6">
                    <div class="inline-block w-8 h-8 border-4 border-indigo-500 rounded-full animate-spin border-t-transparent"></div>
                    <p class="ml-3 text-sm text-gray-600">Đang tải...</p>
                </div>

                <div v-else-if="!weekDetail" class="py-6 text-center">
                    <p class="text-gray-500">Không thể tải thông tin chi tiết.</p>
                </div>

                <div v-else class="mt-4">
                    <!-- Thông tin tuần học -->
                    <div class="p-4 mb-6 rounded-lg bg-gray-50">
                        <h4 class="font-semibold text-gray-700">Tóm tắt</h4>
                        <p class="mt-1 text-sm text-gray-600">{{ weekDetail.week.summary }}</p>

                        <div class="mt-4">
                            <h4 class="font-semibold text-gray-700">Ghi chú</h4>
                            <p class="mt-1 text-sm text-gray-600">{{ weekDetail.week.notes }}</p>
                        </div>

                        <div class="flex items-center mt-4 text-sm text-gray-600">
                            <span>Ngày bắt đầu: </span>
                            <span class="ml-1 font-medium">{{ formatDate(weekDetail.week.start_date) }}</span>
                        </div>
                    </div>

                    <!-- Accordion cho các ngày trong tuần -->
                    <div v-for="day in daysOfWeek" :key="day" class="mb-2 border border-gray-200 rounded-lg">
                        <button @click="toggleDay(day)" class="flex items-center justify-between w-full px-4 py-2 text-sm font-medium text-left text-gray-700 hover:bg-gray-50 focus:outline-none"
                            :class="{ 'bg-gray-50': activeDays.includes(day) }">
                            <span>{{ day }}</span>
                            <div class="flex items-center">
                                <span class="mr-2 text-xs text-gray-500">
                                    {{ getCompletedTasksCount(day) }}/{{ getDayTasks(day).length }} nhiệm vụ
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeDays.includes(day) }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </button>

                        <div v-if="activeDays.includes(day)" class="px-4 py-2 border-t border-gray-200">
                            <div v-if="!getDayTasks(day).length" class="py-2 text-center">
                                <p class="text-sm text-gray-500">Không có nhiệm vụ nào cho ngày này.</p>
                            </div>

                            <div v-for="task in getDayTasks(day)" :key="task.id" class="p-3 mb-2 rounded-lg" :class="{ 'bg-green-50': task.is_done, 'bg-gray-50': !task.is_done }">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 pt-1">
                                        <input type="checkbox" :checked="task.is_done" disabled class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
                                    </div>

                                    <div class="flex-grow ml-3">
                                        <div class="flex flex-col sm:flex-row sm:justify-between">
                                            <h4 class="text-sm font-medium text-gray-900">{{ task.task }}</h4>
                                            <span class="mt-1 text-xs text-gray-500 sm:mt-0">{{ task.duration }}</span>
                                        </div>

                                        <div class="mt-2 space-y-1">
                                            <div class="flex items-center text-xs text-gray-500">
                                                <span class="font-medium">Tài nguyên:</span>
                                                <span class="ml-1">{{ task.resource }}</span>
                                            </div>

                                            <div class="flex items-center text-xs text-gray-500">
                                                <span class="font-medium">Loại:</span>
                                                <span class="ml-1">{{ task.type }}</span>
                                            </div>

                                            <div class="flex items-center text-xs text-gray-500">
                                                <span class="font-medium">Trọng tâm:</span>
                                                <span class="ml-1">{{ task.focus }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    profileId: {
        type: Number,
        required: true
    }
});

const loading = ref(true);
const error = ref('');
const weeks = ref([]);
const showModal = ref(false);
const modalLoading = ref(false);
const weekDetail = ref(null);
const selectedWeekId = ref(null);
const activeDays = ref([]);

const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

onMounted(async () => {
    await fetchWeekHistory();
});

const fetchWeekHistory = async () => {
    loading.value = true;
    error.value = '';

    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        const response = await axios.get(`/api/learning/weeks/history/${props.profileId}`, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`
            }
        });

        if (response.data.status === 200) {
            weeks.value = response.data.data || [];
        } else {
            error.value = response.data.message || 'Không thể tải lịch sử tuần học.';
        }
    } catch (err) {
        console.error('Error fetching week history:', err);
        if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Không thể tải lịch sử tuần học.';
        }
    } finally {
        loading.value = false;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';

    const date = new Date(dateString);
    return new Intl.DateTimeFormat('vi-VN', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(date);
};

const viewWeekDetails = async (weekId) => {
    selectedWeekId.value = weekId;
    showModal.value = true;
    modalLoading.value = true;
    weekDetail.value = null;
    activeDays.value = [];

    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        const response = await axios.get(`/api/learning/tasks/of-week/${weekId}`, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`
            }
        });

        if (response.data.status === 200) {
            weekDetail.value = {
                week: response.data.data.week,
                tasks_by_day: response.data.data.tasks_by_day
            };

            // Mặc định mở ngày đầu tiên có tasks
            for (const day of daysOfWeek) {
                if (response.data.data.tasks_by_day[day]?.length) {
                    activeDays.value = [day];
                    break;
                }
            }
        } else {
            console.error('Error in API response:', response.data);
        }
    } catch (error) {
        console.error('Error fetching week details:', error);
    } finally {
        modalLoading.value = false;
    }
};

const toggleDay = (day) => {
    const index = activeDays.value.indexOf(day);
    if (index === -1) {
        activeDays.value.push(day);
    } else {
        activeDays.value.splice(index, 1);
    }
};

const getDayTasks = (day) => {
    return weekDetail.value?.tasks_by_day[day] || [];
};

const getCompletedTasksCount = (day) => {
    const tasks = getDayTasks(day);
    return tasks.filter(task => task.is_done).length;
};

const downloadPdf = async (weekId) => {
    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        const response = await axios.get(`/api/learning/weeks/${weekId}/export`, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`
            },
            responseType: 'blob' // Quan trọng để nhận file binary
        });

        // Tạo URL cho blob và download file
        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `week-plan-${weekId}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
    } catch (error) {
        console.error('Error downloading PDF:', error);
    }
};
</script>