<template>
    <div class="p-4 bg-white shadow sm:p-8 sm:rounded-lg">
        <div class="max-w-xl">
            <section>
                <header>
                    <h2 class="text-lg font-medium text-gray-900">Xuất kế hoạch học tập</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Xuất kế hoạch học tập của bạn sang định dạng PDF để lưu hoặc in.
                    </p>
                </header>

                <div class="mt-6" v-if="weekId">
                    <button type="button" @click="exportToPDF" :disabled="loading"
                        class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25">
                        <span v-if="loading" class="inline-block w-4 h-4 mr-2 border-2 border-white rounded-full border-t-transparent animate-spin"></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" v-if="!loading">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Xuất PDF
                    </button>
                </div>

                <div v-else class="mt-6 text-sm text-gray-600">
                    Bạn cần tạo kế hoạch học tập trước khi có thể xuất PDF.
                </div>
            </section>
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

const loading = ref(false);
const weekId = ref(null);

onMounted(async () => {
    await fetchCurrentWeek();
});

const fetchCurrentWeek = async () => {
    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        const response = await axios.get(`/api/learning/tasks/${props.profileId}`, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`
            }
        });

        // Lấy week ID từ response
        if (response.data.status === 200 && response.data.data.week) {
            weekId.value = response.data.data.week.id;
        }
    } catch (error) {
        console.error('Error fetching current week:', error);
    }
};

const exportToPDF = async () => {
    if (!weekId.value) return;

    loading.value = true;

    try {
        // Sử dụng API endpoint trực tiếp từ Postman collection
        // Đổi responseType để nhận file PDF
        const response = await axios.get(`/api/learning/weeks/${weekId.value}/export`, {
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
        link.setAttribute('download', `week-plan-${weekId.value}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();

        // Đợi một chút để tải file
        setTimeout(() => {
            loading.value = false;
        }, 2000);
    } catch (error) {
        console.error('Error exporting to PDF:', error);
        loading.value = false;
    }
};
</script>