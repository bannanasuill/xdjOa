<template>
  <div class="admin-stores-page">
    <el-card class="admin-mb-12 admin-page-filters">
      <div class="admin-form-row">
        <el-input
          v-model="query.q"
          placeholder="搜索编码 / 名称"
          clearable
          size="small"
          class="admin-w-240"
          @keyup.enter.native="fetchList"
        />
        <el-button size="small" type="primary" @click="fetchList">查询</el-button>
        <el-button size="small" @click="resetQuery">重置</el-button>
        <span class="admin-flex-spacer"></span>
        <el-button v-if="$canPerm('perm.admin.api.stores.store')" type="primary" size="small" @click="openCreate">
          新增店铺
        </el-button>
      </div>
    </el-card>

    <el-card>
      <el-table
        ref="adminDataTable"
        class="admin-data-table"
        :data="rows"
        :max-height="adminTableMaxHeight"
        size="mini"
        v-loading="loading"
        row-key="id"
      >
        <el-table-column prop="code" label="编码" min-width="108" show-overflow-tooltip />
        <el-table-column prop="name" label="名称" min-width="120" show-overflow-tooltip />
        <el-table-column label="类型" width="88" align="center">
          <template slot-scope="{ row }">{{ storeTypeLabel(row.store_type) }}</template>
        </el-table-column>
        <el-table-column label="所属部门" min-width="120" show-overflow-tooltip>
          <template slot-scope="{ row }">{{ row.dept_name || '—' }}</template>
        </el-table-column>
        <el-table-column prop="address" label="地址" min-width="160" show-overflow-tooltip />
        <el-table-column label="半径(m)" width="82" align="center">
          <template slot-scope="{ row }">{{ row.radius != null ? row.radius : '—' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="92" align="center">
          <template slot-scope="{ row }">
            <el-switch
              v-if="$canPerm('perm.admin.api.stores.status')"
              class="admin-status-switch"
              :value="row.status === 1"
              :active-color="'#13ce66'"
              :inactive-color="'#f56c6c'"
              :disabled="statusBusyId === row.id"
              @change="(on) => patchStatus(row, on ? 1 : 0)"
            />
            <span v-else>{{ row.status === 1 ? '启用' : '停用' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="148" fixed="right" align="left">
          <template slot-scope="{ row }">
            <div class="admin-dept-actions">
              <el-button v-if="$canPerm('perm.admin.api.stores.update')" size="mini" @click="openEdit(row)">
                编辑
              </el-button>
              <el-button
                v-if="$canPerm('perm.admin.api.stores.destroy')"
                type="danger"
                plain
                size="mini"
                :disabled="deleteBusyId === row.id"
                @click="confirmDelete(row)"
              >删除</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="formMode === 'create' ? '新增店铺' : '编辑店铺'" :visible.sync="formVisible" width="600px" @closed="onFormClosed">
      <el-form :model="form" label-width="108px" size="small">
        <el-form-item label="门店编码" required>
          <el-input
            v-model="form.code"
            maxlength="32"
            placeholder="唯一编码"
            :disabled="formMode === 'edit'"
          />
          <div v-if="formMode === 'edit'" class="admin-form-hint">编码创建后不可修改</div>
        </el-form-item>
        <el-form-item label="门店名称" required>
          <el-input v-model="form.name" maxlength="64" placeholder="展示名称" />
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="form.store_type" placeholder="请选择" style="width: 100%">
            <el-option label="门店" :value="1" />
            <el-option label="总部" :value="2" />
            <el-option label="仓库" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="所属部门">
          <el-select v-model="form.dept_id" filterable clearable placeholder="可选，关联分公司等" style="width: 100%">
            <el-option v-for="d in deptOptions" :key="d.id" :label="d.label" :value="d.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="详细地址">
          <div class="admin-stores-address-row">
            <el-input
              v-model="form.address"
              maxlength="255"
              class="admin-stores-address-input"
              placeholder="可输入或点击下方地图选点"
            />
            <el-button
              v-if="$canPerm('perm.admin.api.stores.store') || $canPerm('perm.admin.api.stores.update')"
              size="small"
              :disabled="!baiduBrowserAkConfigured"
              @click="openMapPicker"
            >地图选点</el-button>
          </div>
          <div v-if="!baiduBrowserAkConfigured" class="admin-form-hint">
            地图选点需在 .env 配置 BAIDU_MAP_BROWSER_AK（百度「浏览器端」AK，Referer 填后台域名）。
          </div>
        </el-form-item>
        <el-form-item label="经纬度">
          <div class="admin-stores-latlng-block">
            <div class="admin-stores-latlng">
              <el-input v-model="form.longitude" placeholder="经度 GCJ-02" />
              <el-input v-model="form.latitude" placeholder="纬度 GCJ-02" />
            </div>
            <el-button
              v-if="$canPerm('perm.admin.api.stores.store') || $canPerm('perm.admin.api.stores.update')"
              size="small"
              :loading="geocodeBusy"
              @click="geocodeFromBaidu"
            >百度地图解析</el-button>
            <div class="admin-form-hint">
              「百度地图解析」根据文字地址请求服务端地理编码（GCJ-02），需 BAIDU_MAP_AK（服务端 IP 白名单）。「地图选点」需另配 BAIDU_MAP_BROWSER_AK。
            </div>
          </div>
        </el-form-item>
        <el-form-item label="打卡半径(m)">
          <el-input-number v-model="form.radius" :min="1" :max="500000" :controls="false" style="width: 100%" />
        </el-form-item>
        <el-form-item label="WiFi MAC">
          <el-input v-model="form.wifi_mac" type="textarea" :rows="2" maxlength="255" placeholder="多个用英文逗号分隔，可选" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.statusOn" active-text="启用" inactive-text="停用" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="formVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="formSubmitting" @click="submitForm">保存</el-button>
      </span>
    </el-dialog>

    <el-dialog
      title="地图选点"
      :visible.sync="mapPickerVisible"
      width="720px"
      append-to-body
      custom-class="admin-stores-map-dialog"
      @opened="onMapPickerDialogOpened"
    >
      <div v-loading="mapPickerLoading" class="admin-bmap-picker-wrap">
        <p class="admin-form-hint admin-bmap-picker-hint">
          可先输入地址「定位」到大致区域，再点击地图或拖动标记微调。点「确定选点」后，会回填到店铺表单中的「详细地址」「经度」「纬度」（GCJ-02）。
        </p>
        <div class="admin-bmap-picker-search">
          <el-input
            v-model="mapPickerSearchText"
            size="small"
            clearable
            placeholder="输入地址关键词（省市区+路名等），粗略定位"
            class="admin-bmap-picker-search-input"
            @keyup.enter.native="locateMapPickerByAddress"
          />
          <el-input
            v-model="mapPickerSearchCity"
            size="small"
            clearable
            placeholder="城市（可选）"
            class="admin-bmap-picker-city-input"
          />
          <el-button size="small" type="primary" :loading="mapPickerGeocodeBusy" @click="locateMapPickerByAddress">
            定位
          </el-button>
        </div>
        <div ref="bmapPickerContainer" class="admin-bmap-picker"></div>
      </div>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="mapPickerVisible = false">取消</el-button>
        <el-button size="small" type="primary" @click="confirmMapPicker">确定选点</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import adminTableFixedHeader from '../mixins/adminTableFixedHeader';

/** 百度 JS 与坐标转换脚本，全页只加载一次 */
let baiduMapScriptPromise = null;

function loadBaiduMapScriptsOnce() {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('no window'));
  }
  if (window.BMap && window.BMap.Convertor) {
    return Promise.resolve();
  }
  if (baiduMapScriptPromise) {
    return baiduMapScriptPromise;
  }
  const ak = String(window.__BAIDU_MAP_BROWSER_AK__ || '').trim();
  if (!ak) {
    return Promise.reject(new Error('no browser ak'));
  }
  baiduMapScriptPromise = new Promise((resolve, reject) => {
    const onApiReady = () => {
      delete window.__xdjBmapPickCb;
      const s2 = document.createElement('script');
      s2.src = 'https://api.map.baidu.com/library/Convertor/1.2/src/Convertor_min.js';
      s2.onload = () => resolve();
      s2.onerror = () => {
        baiduMapScriptPromise = null;
        reject(new Error('convertor'));
      };
      document.head.appendChild(s2);
    };
    window.__xdjBmapPickCb = onApiReady;
    const s = document.createElement('script');
    s.src = `https://api.map.baidu.com/api?v=3.0&ak=${encodeURIComponent(ak)}&callback=__xdjBmapPickCb`;
    s.onerror = () => {
      delete window.__xdjBmapPickCb;
      baiduMapScriptPromise = null;
      reject(new Error('bmap api'));
    };
    document.head.appendChild(s);
  });
  return baiduMapScriptPromise;
}

/** BMap.Convertor.translate 回调为 { status:0, points:[] }，勿当作点数组 */
function bmapTranslateFirstPoint(data, fallback) {
  if (!data) {
    return fallback;
  }
  if (data.status === 0 && data.points && data.points.length) {
    return data.points[0];
  }
  if (Array.isArray(data) && data.length) {
    return data[0];
  }
  return fallback;
}

/** 逆地理编码结果取完整地址（兼容 address 或 addressComponents 拼接） */
function formatBaiduGeocoderAddress(rs) {
  if (!rs) {
    return '';
  }
  if (typeof rs.getAddress === 'function') {
    try {
      const s = rs.getAddress();
      if (s) {
        return String(s).trim();
      }
    } catch (e) {
      /* ignore */
    }
  }
  const direct = rs.address != null ? String(rs.address).trim() : '';
  if (direct) {
    return direct;
  }
  const c = rs.addressComponents;
  if (!c || typeof c !== 'object') {
    return '';
  }
  const parts = [c.province, c.city, c.district, c.street, c.streetNumber]
    .map((x) => (x != null ? String(x).trim() : ''))
    .filter(Boolean);
  return parts.join('');
}

const emptyForm = () => ({
  code: '',
  name: '',
  store_type: 1,
  dept_id: null,
  address: '',
  longitude: '',
  latitude: '',
  radius: 100,
  wifi_mac: '',
  statusOn: true,
});

export default {
  name: 'AdminStores',
  mixins: [adminTableFixedHeader],
  data() {
    return {
      loading: false,
      rows: [],
      query: { q: '' },
      deptOptions: [],
      formVisible: false,
      formMode: 'create',
      editingId: null,
      form: emptyForm(),
      formSubmitting: false,
      geocodeBusy: false,
      statusBusyId: null,
      deleteBusyId: null,
      mapPickerVisible: false,
      mapPickerLoading: false,
      bmapPickerMap: null,
      bmapPickerMarker: null,
      mapPickerSearchText: '',
      mapPickerSearchCity: '',
      mapPickerGeocodeBusy: false,
    };
  },
  computed: {
    baiduBrowserAkConfigured() {
      if (typeof window === 'undefined') {
        return false;
      }
      return String(window.__BAIDU_MAP_BROWSER_AK__ || '').trim() !== '';
    },
  },
  created() {
    this.fetchList();
    if (this.$canPerm('perm.admin.api.stores.dept_options')) {
      this.loadDeptOptions();
    }
  },
  methods: {
    storeTypeLabel(t) {
      const n = Number(t);
      if (n === 2) return '总部';
      if (n === 3) return '仓库';
      return '门店';
    },
    resetQuery() {
      this.query.q = '';
      this.fetchList();
    },
    async loadDeptOptions() {
      try {
        const { data } = await window.axios.get('/admin/api/stores/dept-options');
        this.deptOptions = data.data || [];
      } catch (e) {
        this.deptOptions = [];
      }
    },
    async fetchList() {
      if (!this.$canPerm('perm.admin.api.stores.index')) {
        return;
      }
      this.loading = true;
      try {
        const q = (this.query.q || '').trim();
        const { data } = await window.axios.get('/admin/api/stores', {
          params: q ? { q } : {},
        });
        this.rows = data.data || [];
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        this.loading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
    },
    openCreate() {
      if (!this.$canPerm('perm.admin.api.stores.store')) return;
      this.formMode = 'create';
      this.editingId = null;
      this.form = emptyForm();
      this.ensureDeptOptions();
      this.formVisible = true;
    },
    openEdit(row) {
      if (!row || !this.$canPerm('perm.admin.api.stores.update')) return;
      this.formMode = 'edit';
      this.editingId = row.id;
      this.form = {
        code: row.code || '',
        name: row.name || '',
        store_type: row.store_type != null ? Number(row.store_type) : 1,
        dept_id: row.dept_id != null ? Number(row.dept_id) : null,
        address: row.address || '',
        longitude: row.longitude != null && row.longitude !== '' ? String(row.longitude) : '',
        latitude: row.latitude != null && row.latitude !== '' ? String(row.latitude) : '',
        radius: row.radius != null ? Number(row.radius) : 100,
        wifi_mac: row.wifi_mac || '',
        statusOn: row.status === 1,
      };
      this.ensureDeptOptions();
      this.formVisible = true;
    },
    ensureDeptOptions() {
      if ((this.deptOptions || []).length) return;
      if (this.$canPerm('perm.admin.api.stores.dept_options')) {
        this.loadDeptOptions();
      }
    },
    onFormClosed() {
      this.editingId = null;
    },
    openMapPicker() {
      if (!this.baiduBrowserAkConfigured) {
        this.$message.warning('请先在 .env 配置 BAIDU_MAP_BROWSER_AK（百度「浏览器端」AK）');
        return;
      }
      this.mapPickerSearchText = (this.form.address || '').trim();
      this.mapPickerSearchCity = '';
      this.mapPickerVisible = true;
    },
    locateMapPickerByAddress() {
      const { BMap: B } = window;
      const text = (this.mapPickerSearchText || '').trim();
      if (text.length < 2) {
        this.$message.warning('请输入更具体的地址（至少 2 个字符）');
        return;
      }
      if (!B || !this.bmapPickerMap || !this.bmapPickerMarker) {
        this.$message.warning('地图尚未就绪，请稍候再试');
        return;
      }
      const city = (this.mapPickerSearchCity || '').trim();
      this.mapPickerGeocodeBusy = true;
      const geocoder = new B.Geocoder();
      const clearBusy = () => {
        this.mapPickerGeocodeBusy = false;
      };
      const timer = setTimeout(clearBusy, 15000);
      geocoder.getPoint(
        text,
        (point) => {
          clearTimeout(timer);
          clearBusy();
          if (!point) {
            this.$message.warning('未找到该地址，请换关键词或直接在地图上点击');
            return;
          }
          this.bmapPickerMap.centerAndZoom(point, 17);
          this.bmapPickerMarker.setPosition(point);
        },
        city || ''
      );
    },
    async onMapPickerDialogOpened() {
      this.mapPickerLoading = true;
      try {
        await loadBaiduMapScriptsOnce();
        await this.$nextTick();
        this.buildOrRefreshBmapPicker();
        await this.syncBmapPickerFromFormCoords();
      } catch (e) {
        this.$message.error('地图加载失败，请检查 BAIDU_MAP_BROWSER_AK、Referer 白名单与网络');
        this.mapPickerVisible = false;
      } finally {
        this.mapPickerLoading = false;
        this.$nextTick(() => {
          if (this.bmapPickerMap) {
            this.bmapPickerMap.checkResize();
          }
        });
      }
    },
    buildOrRefreshBmapPicker() {
      const { BMap: B } = window;
      const el = this.$refs.bmapPickerContainer;
      if (!B || !el) {
        return;
      }
      const defaultPt = new B.Point(116.404269, 39.915378);
      if (!this.bmapPickerMap) {
        const map = new B.Map(el);
        map.enableScrollWheelZoom(true);
        map.addControl(new B.NavigationControl());
        const marker = new B.Marker(defaultPt);
        marker.enableDragging();
        map.addOverlay(marker);
        map.addEventListener('click', (e) => {
          marker.setPosition(e.point);
        });
        this.bmapPickerMap = map;
        this.bmapPickerMarker = marker;
      }
    },
    syncBmapPickerFromFormCoords() {
      return new Promise((resolve) => {
        const { BMap: B } = window;
        const map = this.bmapPickerMap;
        const marker = this.bmapPickerMarker;
        if (!B || !map || !marker) {
          resolve();
          return;
        }
        const lng = parseFloat(this.form.longitude);
        const lat = parseFloat(this.form.latitude);
        const defaultPt = new B.Point(116.404269, 39.915378);
        if (!Number.isFinite(lng) || !Number.isFinite(lat)) {
          map.centerAndZoom(defaultPt, 12);
          marker.setPosition(defaultPt);
          resolve();
          return;
        }
        const gcjPt = new B.Point(lng, lat);
        const convertor = new B.Convertor();
        convertor.translate([gcjPt], 3, 5, (data) => {
          const pt = bmapTranslateFirstPoint(data, defaultPt);
          map.centerAndZoom(pt, 16);
          marker.setPosition(pt);
          resolve();
        });
      });
    },
    confirmMapPicker() {
      const { BMap: B } = window;
      const marker = this.bmapPickerMarker;
      const map = this.bmapPickerMap;
      if (!B || !marker || !map) {
        this.mapPickerVisible = false;
        return;
      }
      const bdPt = marker.getPosition();
      const geocoder = new B.Geocoder();
      const convertor = new B.Convertor();
      geocoder.getLocation(bdPt, (rs) => {
        let addr = formatBaiduGeocoderAddress(rs);
        if (!addr) {
          addr = (this.mapPickerSearchText || '').trim();
        }
        if (!addr) {
          addr = (this.form.address || '').trim();
        }
        if (addr.length > 255) {
          addr = addr.slice(0, 255);
        }
        convertor.translate([bdPt], 5, 3, (data) => {
          const p = bmapTranslateFirstPoint(data, null);
          if (!p) {
            const code = data && data.status != null ? `（状态码 ${data.status}）` : '';
            this.$message.error(`坐标转换失败${code}，请重试`);
            return;
          }
          const lon = String(Math.round(p.lng * 1e6) / 1e6);
          const lat = String(Math.round(p.lat * 1e6) / 1e6);
          this.$set(this.form, 'longitude', lon);
          this.$set(this.form, 'latitude', lat);
          this.$set(this.form, 'address', addr);
          const hasAddr = addr.length > 0;
          this.$message.success(
            hasAddr ? '已回填详细地址与经纬度（GCJ-02）' : '已回填经纬度（GCJ-02），请补充详细地址'
          );
          this.$nextTick(() => {
            this.mapPickerVisible = false;
          });
        });
      });
    },
    geocodePayload() {
      const payload = {
        address: (this.form.address || '').trim() || null,
      };
      const pn = this.form.province_name;
      const cn = this.form.city_name;
      const dn = this.form.district_name;
      if (pn != null && String(pn).trim() !== '') {
        payload.province_name = String(pn).trim();
      }
      if (cn != null && String(cn).trim() !== '') {
        payload.city_name = String(cn).trim();
      }
      if (dn != null && String(dn).trim() !== '') {
        payload.district_name = String(dn).trim();
      }

      return payload;
    },
    async geocodeFromBaidu() {
      if (!this.$canPerm('perm.admin.api.stores.store') && !this.$canPerm('perm.admin.api.stores.update')) {
        return;
      }
      const payload = this.geocodePayload();
      const hasDetail = payload.address != null && payload.address !== '';
      const hasRegion = payload.province_name || payload.city_name || payload.district_name;
      if (!hasDetail && !hasRegion) {
        this.$message.warning('请先填写详细地址（建议含省市区）');
        return;
      }
      this.geocodeBusy = true;
      try {
        const { data } = await window.axios.post('/admin/api/stores/geocode', payload);
        const loc = data.data;
        if (loc && loc.longitude != null && loc.latitude != null) {
          this.form.longitude = String(loc.longitude);
          this.form.latitude = String(loc.latitude);
          this.$message.success(data.message || '已解析');
        }
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          (e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().filter(Boolean).join('；') : null) ||
          '解析失败';
        this.$message.error(msg);
      } finally {
        this.geocodeBusy = false;
      }
    },
    async submitForm() {
      const code = (this.form.code || '').trim();
      const name = (this.form.name || '').trim();
      if (!code) {
        this.$message.warning('请填写门店编码');
        return;
      }
      if (!name) {
        this.$message.warning('请填写门店名称');
        return;
      }
      const payload = {
        code,
        name,
        store_type: this.form.store_type != null ? Number(this.form.store_type) : 1,
        dept_id: this.form.dept_id != null && this.form.dept_id !== '' ? Number(this.form.dept_id) : null,
        address: (this.form.address || '').trim() || null,
        radius: this.form.radius != null ? Number(this.form.radius) : 100,
        wifi_mac: (this.form.wifi_mac || '').trim() || null,
        status: this.form.statusOn ? 1 : 0,
      };
      const lon = (this.form.longitude || '').trim();
      const lat = (this.form.latitude || '').trim();
      payload.longitude = lon !== '' ? lon : null;
      payload.latitude = lat !== '' ? lat : null;

      this.formSubmitting = true;
      try {
        if (this.formMode === 'create') {
          await window.axios.post('/admin/api/stores', payload);
          this.$message.success('已创建');
        } else {
          await window.axios.put(`/admin/api/stores/${this.editingId}`, payload);
          this.$message.success('已保存');
        }
        this.formVisible = false;
        this.fetchList();
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          (e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().filter(Boolean).join('；') : null) ||
          '保存失败';
        this.$message.error(msg);
      } finally {
        this.formSubmitting = false;
      }
    },
    async patchStatus(row, status) {
      if (!this.$canPerm('perm.admin.api.stores.status')) return;
      const prev = row.status;
      this.statusBusyId = row.id;
      row.status = status;
      try {
        await window.axios.patch(`/admin/api/stores/${row.id}/status`, { status });
        this.$message.success('状态已更新');
      } catch (e) {
        row.status = prev;
        this.$message.error(e?.response?.data?.message || '更新失败');
      } finally {
        this.statusBusyId = null;
      }
    },
    async confirmDelete(row) {
      if (!row || !this.$canPerm('perm.admin.api.stores.destroy')) return;
      const label = (row.name && String(row.name).trim()) || row.code || `ID ${row.id}`;
      try {
        await this.$confirm(`确定删除店铺「${label}」吗？`, '删除确认', {
          type: 'warning',
          confirmButtonText: '删除',
          cancelButtonText: '取消',
        });
      } catch (e) {
        return;
      }
      this.deleteBusyId = row.id;
      try {
        await window.axios.delete(`/admin/api/stores/${row.id}`);
        this.$message.success('已删除');
        this.fetchList();
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '删除失败');
      } finally {
        this.deleteBusyId = null;
      }
    },
  },
};
</script>

<style scoped>
.admin-stores-latlng-block {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
}

.admin-stores-latlng {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  width: 100%;
}

.admin-stores-address-row {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.admin-stores-address-input {
  flex: 1;
  min-width: 0;
}

.admin-bmap-picker-wrap {
  min-height: 420px;
}

.admin-bmap-picker-hint {
  margin: 0 0 8px;
}

.admin-bmap-picker-search {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.admin-bmap-picker-search-input {
  flex: 1;
  min-width: 200px;
}

.admin-bmap-picker-city-input {
  width: 120px;
}

.admin-bmap-picker {
  width: 100%;
  height: 400px;
  border-radius: 4px;
  overflow: hidden;
  background: #e8ecf0;
}
</style>
