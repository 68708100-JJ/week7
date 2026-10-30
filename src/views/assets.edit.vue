<template>
  <div class="container mt-4">
    <h2 class="mb-3">รายการครุภัณฑ์</h2>

    <div class="mb-3">
      <button class="btn btn-primary" @click="openAddModal">เพิ่มครุภัณฑ์</button>
    </div>

    <div v-if="loading" class="text-center">
      <p>กำลังโหลดข้อมูล...</p>
    </div>
    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <table v-else class="table table-bordered table-striped">
      <thead class="table-primary">
        <tr>
          <th>รหัส</th>
          <th>ชื่อครุภัณฑ์</th>
          <th>วันที่ซื้อ</th>
          <th>ราคา</th>
          <th>รูปภาพ</th>
          <th>การจัดการ</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="asset in assets" :key="asset.asset_id">
          <td>{{ asset.asset_code }}</td>
          <td>{{ asset.asset_name }}</td>
          <td>{{ asset.purchase_date || "-" }}</td>
          <td>{{ formatPrice(asset.price) }}</td>
          <td>
            <img
              v-if="asset.image"
              :src="imageUrl(asset.image)"
              :alt="asset.asset_name"
              width="100"
              @error="hideBrokenImage"
            />
            <span v-else>-</span>
          </td>
          <td>
            <button class="btn btn-warning btn-sm me-2" @click="openEditModal(asset)">
              แก้ไข
            </button>
            <button class="btn btn-danger btn-sm" @click="deleteAsset(asset.asset_id)">
              ลบ
            </button>
          </td>
        </tr>
        <tr v-if="assets.length === 0">
          <td colspan="6" class="text-center">ไม่มีข้อมูลครุภัณฑ์</td>
        </tr>
      </tbody>
    </table>

    <div ref="modalElement" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ isEditMode ? "แก้ไขครุภัณฑ์" : "เพิ่มครุภัณฑ์" }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveAsset">
              <div class="mb-3">
                <label class="form-label">รหัสครุภัณฑ์</label>
                <input v-model.trim="editForm.asset_code" type="text" class="form-control" maxlength="50" required />
              </div>
              <div class="mb-3">
                <label class="form-label">ชื่อครุภัณฑ์</label>
                <input v-model.trim="editForm.asset_name" type="text" class="form-control" maxlength="255" required />
              </div>
              <div class="mb-3">
                <label class="form-label">วันที่ซื้อ</label>
                <input v-model="editForm.purchase_date" type="date" class="form-control" />
              </div>
              <div class="mb-3">
                <label class="form-label">ราคา</label>
                <input v-model="editForm.price" type="number" min="0" step="0.01" class="form-control" />
              </div>
              <div class="mb-3">
                <label class="form-label">รูปภาพ</label>
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  class="form-control"
                  @change="handleFileUpload"
                />
                <div v-if="isEditMode && editForm.image" class="mt-2">
                  <p>รูปภาพปัจจุบัน:</p>
                  <img
                    :src="imageUrl(editForm.image)"
                    :alt="editForm.asset_name"
                    width="100"
                    @error="hideBrokenImage"
                  />
                </div>
                <small class="form-text">รองรับ JPG, PNG, WebP หรือ GIF ขนาดไม่เกิน 5 MB</small>
              </div>
              <button type="submit" class="btn btn-success" :disabled="saving">
                {{ saving ? "กำลังบันทึก..." : isEditMode ? "บันทึกการแก้ไข" : "เพิ่มครุภัณฑ์" }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { onBeforeUnmount, onMounted, ref } from "vue";

const API_URL = "http://localhost/week7/php_api/asset.php";
const UPLOADS_URL = "http://localhost/week7/php_api/uploads/";

const emptyAsset = () => ({
  asset_id: null,
  asset_code: "",
  asset_name: "",
  purchase_date: "",
  price: "",
  image: ""
});

export default {
  name: "AssetList",
  setup() {
    const assets = ref([]);
    const loading = ref(true);
    const saving = ref(false);
    const error = ref(null);
    const isEditMode = ref(false);
    const editForm = ref(emptyAsset());
    const newImageFile = ref(null);
    const modalElement = ref(null);
    const fileInput = ref(null);
    let modalInstance = null;

    const fetchAssets = async () => {
      loading.value = true;
      error.value = null;

      try {
        const response = await fetch(API_URL);
        const result = await response.json();

        if (!response.ok || !result.success || !Array.isArray(result.data)) {
          throw new Error(result.message || "ไม่สามารถโหลดข้อมูลครุภัณฑ์ได้");
        }

        assets.value = result.data;
      } catch (err) {
        error.value = err.message;
      } finally {
        loading.value = false;
      }
    };

    const resetForm = () => {
      editForm.value = emptyAsset();
      newImageFile.value = null;
      if (fileInput.value) fileInput.value.value = "";
    };

    const openAddModal = () => {
      isEditMode.value = false;
      resetForm();
      modalInstance.show();
    };

    const openEditModal = (asset) => {
      isEditMode.value = true;
      editForm.value = { ...asset };
      newImageFile.value = null;
      if (fileInput.value) fileInput.value.value = "";
      modalInstance.show();
    };

    const handleFileUpload = (event) => {
      newImageFile.value = event.target.files?.[0] || null;
    };

    const saveAsset = async () => {
      const formData = new FormData();
      formData.append("action", isEditMode.value ? "update" : "add");

      if (isEditMode.value) {
        formData.append("asset_id", editForm.value.asset_id);
      }
      formData.append("asset_code", editForm.value.asset_code);
      formData.append("asset_name", editForm.value.asset_name);
      formData.append("purchase_date", editForm.value.purchase_date || "");
      formData.append("price", editForm.value.price ?? "");
      if (newImageFile.value) {
        formData.append("image", newImageFile.value);
      }

      saving.value = true;
      try {
        const response = await fetch(API_URL, { method: "POST", body: formData });
        const result = await response.json();

        if (!response.ok || !result.success) {
          throw new Error(result.message || "ไม่สามารถบันทึกข้อมูลครุภัณฑ์ได้");
        }

        modalInstance.hide();
        await fetchAssets();
        alert(result.message);
      } catch (err) {
        alert("เกิดข้อผิดพลาด: " + err.message);
      } finally {
        saving.value = false;
      }
    };

    const deleteAsset = async (id) => {
      if (!confirm("คุณต้องการลบครุภัณฑ์นี้ใช่หรือไม่?")) return;

      const formData = new FormData();
      formData.append("action", "delete");
      formData.append("asset_id", id);

      try {
        const response = await fetch(API_URL, { method: "POST", body: formData });
        const result = await response.json();

        if (!response.ok || !result.success) {
          throw new Error(result.message || "ไม่สามารถลบข้อมูลครุภัณฑ์ได้");
        }

        assets.value = assets.value.filter((asset) => String(asset.asset_id) !== String(id));
        alert(result.message);
      } catch (err) {
        alert("เกิดข้อผิดพลาด: " + err.message);
      }
    };

    const formatPrice = (price) => {
      if (price === null || price === "") return "-";
      return Number(price).toLocaleString("th-TH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    };

    const imageUrl = (filename) => `${UPLOADS_URL}${encodeURIComponent(filename)}`;
    const hideBrokenImage = (event) => {
      event.target.hidden = true;
    };

    onMounted(() => {
      if (!window.bootstrap?.Modal) {
        throw new Error("ไม่สามารถโหลด Bootstrap Modal ได้");
      }
      modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalElement.value);
      fetchAssets();
    });

    onBeforeUnmount(() => {
      modalInstance?.dispose();
    });

    return {
      assets,
      loading,
      saving,
      error,
      isEditMode,
      editForm,
      modalElement,
      fileInput,
      openAddModal,
      openEditModal,
      handleFileUpload,
      saveAsset,
      deleteAsset,
      formatPrice,
      imageUrl,
      hideBrokenImage
    };
  }
};
</script>
