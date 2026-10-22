import { Module } from "@nestjs/common";
import { AdminModule } from "./modules/admin/admin.module";
import { AuditModule } from "./modules/audit/audit.module";
import { AuthModule } from "./modules/auth/auth.module";
import { BookingsModule } from "./modules/bookings/bookings.module";
import { BusinessesModule } from "./modules/businesses/businesses.module";
import { CatalogModule } from "./modules/catalog/catalog.module";
import { DiscoveryModule } from "./modules/discovery/discovery.module";
import { FavoritesModule } from "./modules/favorites/favorites.module";
import { HealthModule } from "./modules/health/health.module";
import { MessagingModule } from "./modules/messaging/messaging.module";
import { NotificationsModule } from "./modules/notifications/notifications.module";
import { PromotionsModule } from "./modules/promotions/promotions.module";
import { ReviewsModule } from "./modules/reviews/reviews.module";
import { SchedulingModule } from "./modules/scheduling/scheduling.module";
import { UsersModule } from "./modules/users/users.module";

@Module({
  imports: [
    HealthModule,
    AuthModule,
    UsersModule,
    BusinessesModule,
    CatalogModule,
    DiscoveryModule,
    SchedulingModule,
    BookingsModule,
    FavoritesModule,
    PromotionsModule,
    ReviewsModule,
    MessagingModule,
    NotificationsModule,
    AdminModule,
    AuditModule,
  ],
})
export class AppModule {}

